<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\Roles;
use App\Enums\VisitationStatus;
use App\Models\Admission;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visitation;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VisitationController extends Controller
{
    private function getVisitationFinancialSummary($visitationId)
    {
        try {
            $visitation = Visitation::with('patient.wallet')->findOrFail($visitationId);

            $patientId = $visitation->patient_id;
            $wallet = $visitation->patient?->wallet;
            $now = now();

            // Calculate days spent
            $visitationDate = Carbon::parse($visitation->admission_date);
            $daysSpent = (int) $visitationDate->diffInDays($now) ?: 1;

            // consultation cost
            $consultationService = Service::where('name', 'Consulation')->first();
            $consultationPrice = $consultationService?->price ?? 500;
            $consultationCost = $consultationPrice * $daysSpent;

            // Fetch treatment
            $treatments = DB::table('treatments')
                ->where('visitation_id', $visitationId)
                ->orderBy('created_at', 'asc')
                ->get();

            Log::info($treatments->count(), [$visitationId]);

            $treatmentIds = $treatments->pluck('id');

            // Fetch lab requests linked to treatments
            $labRequests = DB::table('lab_requests as lr')
                ->join('services as s', 's.id', '=', 'lr.service_id')
                ->whereIn('lr.treatment_id', $treatmentIds)
                ->select(
                    'lr.id',
                    'lr.treatment_id',
                    'lr.priority',
                    's.name as service_name',
                    's.price as service_price'
                )
                ->get();

            $treatmentItems = DB::table('treatment_items as ti')
                ->leftJoin('products as p', 'p.id', '=', 'ti.product_id')
                ->whereIn('ti.treatment_id', $treatmentIds)
                ->select(
                    'ti.id as treatment_item_id',
                    'ti.treatment_id',
                    'p.brand_name as item_name',
                    'p.unit_price',
                    'ti.quantity',
                    DB::raw('(COALESCE(ti.quantity,0) * COALESCE(p.unit_price,0)) as total_charge')
                )
                ->get();


            // Item cost: sum of all product charges
            $itemsCost = $treatmentItems->sum('total_charge');
            $testsCost = $labRequests->sum('service_price');
            $treatmentSessionCost = $treatments->count() * 1000;
            $treatmentCost = $treatmentSessionCost + $itemsCost;

            // Attach items + tests (lab requests) to treatments
            $treatmentsWithItems = $treatments->map(function ($treatment) use ($treatmentItems, $labRequests) {
                $items = $treatmentItems->where('treatment_id', $treatment->id)->values();
                $tests = $labRequests->where('treatment_id', $treatment->id)->values();

                return (object) [
                    'id' => $treatment->id,
                    'treatment_date' => $treatment->treatment_date,
                    'treatment_type' => $treatment->treatment_type,
                    'items' => $items,
                    'tests' => $tests,
                ];
            });

            // Calculate costs and generate records
            $records = [];
            $balance = 0;
            $treatmentCost = 0;

            foreach ($treatmentsWithItems as $treatment) {
                // Session charge (₦1000 per treatment)
                $balance += 1000;
                $treatmentCost += 1000;
                $records[] = [
                    'date' => $treatment->treatment_date,
                    'qty' => 1,
                    'particular' => $treatment->treatment_type . ' Treatment Charge',
                    'charges' => 1000,
                    'credit' => 0,
                    'balance' => $balance,
                ];

                // Add items for the treatment
                foreach ($treatment->items as $item) {
                    $balance += $item->total_charge;
                    $treatmentCost += $item->total_charge;
                    $records[] = [
                        'date' => $treatment->treatment_date,
                        'qty' => $item->quantity,
                        'particular' => $item->item_name,
                        'charges' => $item->total_charge,
                        'credit' => 0,
                        'balance' => $balance,
                    ];
                }

                foreach ($treatment->tests as $test) {
                    $balance += $test->service_price;
                    $treatmentCost += $test->service_price;
                    $records[] = [
                        'date' => $treatment->treatment_date,
                        'qty' => 1,
                        'particular' => $test->service_name . ' Test',
                        'charges' => (int) $test->service_price,
                        'credit' => 0,
                        'balance' => $balance,
                    ];
                }
            }

            // Payments
            $payments = Payment::where('patient_id', $patientId)
                ->where('payable_type', Visitation::class)
                ->where('payable_id', $visitationId)
                ->where('status', 'COMPLETED')
                ->select('id', 'amount', 'created_at as payment_date', 'transaction_reference')
                ->orderBy('created_at', 'asc')
                ->get();

            // consultation record
            $balance += $consultationCost;
            $records[] = [
                'date' => $visitation->start_date ? Carbon::parse($visitation->start_date)->format('Y-m-d') : null,
                'qty' => 1,
                'particular' => 'Consultation Charge',
                'charges' => $consultationCost,
                'credit' => 0,
                'balance' => $balance,
            ];

            // Add payment records
            foreach ($payments as $pay) {
                $balance -= $pay->amount;
                $records[] = [
                    'date' => $pay->payment_date ? Carbon::parse($pay->payment_date)->format('Y-m-d') : null,
                    'qty' => 1,
                    'particular' => 'Payment (Ref: ' . $pay->transaction_reference . ')',
                    'charges' => 0,
                    'credit' => $pay->amount,
                    'balance' => $balance,
                ];
            }

            // Totals
            $totalCharges = $consultationCost + $treatmentCost;
            $totalPayments = $payments->sum('amount');
            $balanceDue = max(0, $totalCharges - $totalPayments);
            $depositBalance = $wallet?->deposit_balance ?? 0;

            return [
                'visitation' => [
                    'id' => $visitation->id,
                    'patient_id' => $patientId,
                    'visitation_date' => $visitation->visitation_date,
                    'discharge_date' => $visitation->discharge_date,
                    'days_spent' => $daysSpent,
                ],
                'summary' => [
                    'records' => $records,
                    'consultation_cost' => (int) $consultationCost,
                    'treatment_session_cost' => (int) $treatmentSessionCost,
                    'item_cost' => (float) $itemsCost,
                    'test_cost' => (float) $testsCost,
                    'treatment_cost' => (int) $treatmentCost,
                    'total_charges' => (float) $totalCharges,
                    'total_payments' => (float) $totalPayments,
                    'balance_due' => (float) $balanceDue,
                    'deposit_balance' => (float) $depositBalance,
                    'outstanding_balance' => max(0, $balanceDue - $depositBalance),
                ]
            ];
        } catch (Exception $e) {
            Log::error('Financial summary error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'patient' => ['required', 'integer'],
                'assigned_doctor' => ['required', 'integer'],
                'payment_reference' => ['required', 'string'],
                'date' => ['required', 'date_format:Y-m-d'],
                'time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
            [
                'patient.required' => 'Patient detail is required',
                'patient.integer' => 'Patient detail contains invalid data',
                'assigned_doctor.required' => 'Assigned doctor detail is required',
                'assigned_doctor.integer' => 'Assigned doctor detail contains invalid data',
                'payment_reference.required' => 'Payment reference detail is required',
                'date.required' => 'Date is required',
                'date.date_format' => 'Date contains invalid data',
                'time.required' => 'Time is required',
                'time.regex' => 'Time is invalid',
            ]
        );

        try {
            $staff = Auth::user();

            $patient = Patient::find($validated['patient']);
            if (!$patient) {
                throw new BadRequestHttpException('Patient detail not found');
            }

            $doctor = User::find($validated['assigned_doctor']);
            if (!$doctor) {
                throw new BadRequestHttpException('Assigned doctor detail not found');
            }

            $overlappingVisitations = $this->findOverlapVisitations($validated['date'], $validated['time']);
            if ($overlappingVisitations->count() > 0) {
                throw new BadRequestHttpException('The visitation overlaps with others');
            }

            $patientTodayVisitations = $this->findNumberOfVisitationsForPatient($patient->id, $validated['date']);
            if ($patientTodayVisitations->count() > 1) {
                throw new BadRequestHttpException('Patient cannot book a visitation more than twice per day');
            }

            $payment = Payment::with('service')->where('transaction_reference', $validated['payment_reference'])->first();

            if (!$payment) {
                throw new BadRequestHttpException('Payment detail not found');
            }

            Log::info($payment->patient_id);

            if ($payment->patient_id != $validated["patient"]) {
                throw new BadRequestHttpException('Payment reference is not for the selected patient');
            }

            if ($payment->is_used) {
                throw new BadRequestHttpException('You need to make a payment. The reference has been used before');
            }

            if ($payment->status != PaymentStatus::COMPLETED->value) {
                throw new BadRequestHttpException('You need to make a payment before you can continue');
            }

            if ($payment->payable_type != Visitation::class) {
                throw new BadRequestHttpException('This payment is not intended for visitation');
            }

            $endTime = Carbon::createFromFormat('H:i', $validated['time'])->addMinutes(20)->format('H:i');

            $visitation = new Visitation();
            $visitation->end_time = $endTime;
            $visitation->start_date = $validated['date'];
            $visitation->start_time = $validated['time'];
            $visitation->history = json_encode([
                [
                    'title' => VisitationStatus::PENDING,
                    'date' => now(),
                    'created_by' => $staff->fullname,
                    'staff_detail' => $staff->staff_id,
                ]
            ]);
            $visitation->assignedDoctor()->associate($doctor);
            $visitation->patient()->associate($patient);
            $visitation->createdBy()->associate($staff);
            $visitation->lastUpdatedBy()->associate($staff);
            $visitation->save();

            // Associate the payment after visitation is saved
            $payment->payable_id = $visitation->id;
            $payment->is_used = true;
            $payment->save();

            return response()->json([
                'message' => 'Visitation created successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::error('Error creating visitation: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again');
        }
    }

    public function findAll(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $status = $request->get('status', '');
            $q = $request->get('q', '');
            $limit = $request->get('limit', 50);

            $queryBuilder = Visitation::with(['assignedDoctor', 'patient', 'recommendedTests.service', 'payment'])
                ->orderByDesc('updated_at')
                ->when($q, function ($query, $q) {
                    $query->whereHas('patient', function ($patientQ) use ($q) {
                        $patientQ->where(function ($qb) use ($q) {
                            $qb->where('firstname', 'LIKE', "%{$q}%")
                                ->orWhere('lastname',  'LIKE', "%{$q}%")
                                ->orWhere('phone_number', 'LIKE', "%{$q}%")
                                ->orWhere('patient_reg_no', 'LIKE', "%{$q}%");
                        });
                    });
                });

            if (!empty($status) && strtoupper($status) != 'ALL') {
                $queryBuilder->where('status', $status);
            }

            if ($user->role == Roles::DOCTOR->value) {
                $queryBuilder->where('assigned_doctor_id', $user->id);
            }

            // if ($user->role === Roles::NURSE) {
            //     $queryBuilder->whereHas('assigned_doctor.assigned_branch', fn($q) => $q->where('id', $user->assigned_branch->id));
            // }

            $visitations = $queryBuilder->paginate($limit);

            return response()->json([
                'message' => 'Visitations fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $visitations,
            ]);
        } catch (HttpException $e) {
            Log::error('Error fetching visitations: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again later');
        }
    }

    public function findOne($id)
    {
        try {
            $visitation = Visitation::with([
                'createdBy:id,firstname,lastname',
                'lastUpdatedBy:id,firstname,lastname',
                'patient.physicalExaminations.addedBy.assignedBranch',
                'patient.labRequests',
                'patient.wallet',
                'patient.organisationHmo:id,name,type,phone_number',
                'patient.labRequests.testResult',
                'patient.labRequests.service',
                'patient.vitalSigns',
                'patient.treatments.createdBy.assignedBranch',
                'assignedDoctor.assignedBranch',
                'physicalExaminations',
                'recommendedTests.service',
                'recommendedTests.testResult',
                'treatment.patient',
                'prescriptions',
                'payment:id,status',
                'prescriptions.requestedBy',
                'prescriptions.items',
                'prescriptions.items.product',
                'prescriptions.notes' => function ($query) {
                    $query->limit(1);
                }
            ])->where('id', $id)->first();

            if (!$visitation) {
                throw new BadRequestHttpException('Visitation detail not found');
            }

            // Previous prescriptions
            $previousPrescriptions = Prescription::with(['requestedBy', 'items', 'items.product'])
                ->where('patient_id', $visitation->patient_id)
                ->where('created_at', '<', $visitation->created_at)
                ->orderBy('created_at', 'desc')
                ->get();

            $visitation->previousPrescriptions = $previousPrescriptions;

            // Financial Summary
            $financialSummary = $this->getVisitationFinancialSummary($visitation->id);

            // Merge everything into one structured response
            return response()->json([
                'message' => 'Visitation fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => array_merge($visitation->toArray(), [
                    'financial_breakdown' => $financialSummary,
                ]),
            ]);
        } catch (BadRequestHttpException $e) {
            Log::error('Visitation fetch error: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }


    // public function findOne($id)
    // {
    //     try {
    //         $visitation = Visitation::with([
    //             'createdBy:id,firstname,lastname',
    //             'lastUpdatedBy:id,firstname,lastname',
    //             'patient.physicalExaminations.addedBy.assignedBranch',
    //             'patient.labRequests',
    //             'patient.wallet',
    //             'patient.organisationHmo:id,name,type,phone_number',
    //             'patient.labRequests.testResult',
    //             'patient.labRequests.service',
    //             'patient.vitalSigns',
    //             'patient.treatments.createdBy.assignedBranch',
    //             'assignedDoctor.assignedBranch',
    //             'physicalExaminations',
    //             'recommendedTests.service',
    //             'recommendedTests.testResult',
    //             'treatment.patient',
    //             'prescriptions',
    //             'payment:id,status',
    //             'prescriptions.requestedBy',
    //             'prescriptions.items',
    //             'prescriptions.items.product',
    //             'prescriptions.notes' => function ($query) {
    //                 $query->limit(1);
    //             }
    //         ])->where('id', $id)->first();

    //         if (!$visitation) {
    //             throw new BadRequestHttpException('Visitation detail not found');
    //         }

    //         $previousPrescriptions = Prescription::with(['requestedBy', 'items', 'items.product'])
    //             ->where('patient_id', $visitation->patient_id)
    //             ->where('created_at', '<', $visitation->created_at)
    //             ->orderBy('created_at', 'desc')
    //             ->get();

    //         $visitation->previousPrescriptions = $previousPrescriptions;


    //         return response()->json([
    //             'message' => 'Visitation Fetched Successfully',
    //             'status' => 'success',
    //             'success' => true,
    //             'data' => $visitation
    //         ]);
    //     } catch (BadRequestHttpException $e) {
    //         Log::error('Visitation fetch error: ' . $e->getMessage());
    //         throw $e;
    //     } catch (Exception $e) {
    //         Log::error('Unexpected error: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function accept($id)
    {
        try {
            $user = Auth::user();
            $visitation = Visitation::find($id);

            if (!$visitation) {
                throw new BadRequestHttpException('The Visitation detail not found');
            }

            Log::info($visitation->status);

            if ($visitation->status == VisitationStatus::ACCEPTED->value) {
                return response()->json([
                    'message' => 'Visitation already marked as accepted',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            Log::info("Authenticated user id: $user->id, Assigned Doctor Id: $visitation->assigned_doctor_id");

            if ($user->id != $visitation->assigned_doctor_id) {
                return response()->json([
                    'message' => "You cannot accept because the visitation was not assigned to you.",
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $visitation->status = VisitationStatus::ACCEPTED;
            $visitation->save();

            return response()->json([
                'message' => 'Visitation Updated Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::info('Error accepting Visitation: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::info('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function reschedule(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(
            [
                'date' => ['required', 'date_format:Y-m-d'],
                'time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/']
            ],
            [
                'date.required' => 'Date is required',
                'date.date_format' => 'Date format is invalid',
                'time.required' => 'Time is required',
                'time.regex' => 'Time format is invalid'
            ]
        );

        try {
            $visitation = Visitation::with('patient')->find($id);

            if (!$visitation) {
                throw new BadRequestHttpException('The Visitation detail not found');
            }

            if ($visitation->status === VisitationStatus::CANCELLED) {
                throw new BadRequestHttpException('You cannot reschedule a cancelled Visitation');
            }

            if ($visitation->status === VisitationStatus::CONSULTED) {
                throw new BadRequestHttpException('You cannot reschedule a consulted appointment');
            }

            // `$hour = (int) explode(':', $validated['time'])[0];
            // if ($hour < 8 || $hour > 15) {
            //     throw new BadRequestHttpException('Appointment time should be between 8 AM and 4 PM');
            // }`

            // Create the start date-time object
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $validated['date'] . ' ' . $validated['time']);

            // Calculate the end time by adding the appointment duration in minutes
            $endDateTime = $startDateTime->copy()->addMinutes($visitation->duration);

            // Check for overlapping appointments
            $overlapAppointments = $this->findOverlapVisitations($validated['date'], $validated['time'], $id);
            if ($overlapAppointments->count() > 0) {
                throw new BadRequestHttpException('The appointment overlaps with others');
            }

            // Check the number of appointments for the patient on the same day
            $patientTodayAppointments = $this->findNumberOfVisitationsForPatient(
                $visitation->patient->id,
                $validated['date']
            );

            if ($patientTodayAppointments->count() > 1) {
                throw new BadRequestHttpException(
                    'Patient cannot book an appointment more than twice per day'
                );
            }

            // Update the appointment details
            $visitation->start_date = $validated['date'];
            $visitation->start_time = $validated['time'];
            $visitation->end_time = $endDateTime->format('H:i:s');
            $visitation->save();

            return response()->json([
                'message' => 'Appointment Rescheduled Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (HttpException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected Error: ' . $e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function approveOrCancel(string $id, string $status)
    {
        try {
            if (!in_array($status, ['approve', 'cancel'])) {
                throw new BadRequestHttpException('Invalid status provided. Status must be either "approve" or "cancel".');
            }

            $user = Auth::user();

            $visitation = Visitation::with('assignedDoctor')->find($id);

            if (!$visitation) {
                throw new BadRequestHttpException('The visitation detail not found');
            }

            if ($user->id != $visitation->assigned_doctor_id) {
                return response()->json([
                    'message' => "You cannot $status because the visitation was not assigned to you.",
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($status === 'approve') {
                $visitation->status = VisitationStatus::CONSULTED;
            } elseif ($status === 'cancel') {
                $visitation->status = VisitationStatus::CANCELLED;
            } else {
                throw new BadRequestHttpException('Invalid status provided');
            }

            $historyEntry = [
                'user_id' => $user->id,
                'user_staff_number' => $user->staff_number ?? null,
                'action' => $visitation->status,
                'timestamp' => now()->toDateTimeString(),
            ];

            $history = json_decode((string) $visitation->history, true) ?? [];
            $history[] = $historyEntry;
            $visitation->history = json_encode($history);
            $visitation->save();

            return response()->json([
                'message' => 'Visitation Updated Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::error('Error updating appointment: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAllAppointmentsForToday()
    {
        try {
            $user = Auth::user();

            $today = Carbon::today()->format('Y-m-d');

            $query = Visitation::with([
                'assignedDoctor',
                'patient.vitalSigns' => function ($query) {
                    $query->orderBy('created_at', 'desc');
                }
            ])->whereDate('start_date', $today);

            if ($user->role === 'DOCTOR') {
                $query->where('assigned_doctor_id', $user->id);
            }

            if ($user->role === 'NURSE' && $user->assigned_branch_id) {
                $query->whereHas('assignedDoctor.assignedBranch', function ($q) use ($user) {
                    $q->where('id', $user->assigned_branch_id);
                });
            }

            $visitations = $query->orderByDesc('created_at')->paginate(20);

            return response()->json([
                'message' => 'Vistation fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $visitations
            ]);
        } catch (Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function addConsultationReport(string $id, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        try {
            $visitation = Visitation::with('patient')->find($id);

            if (!$visitation) {
                return response()->json([
                    'message' => 'The visitation detail not found',
                    'status' => 'success',
                    'success' => true,
                ], 400);
            }

            if (in_array($visitation->status, [
                VisitationStatus::CONSULTED->value,
                VisitationStatus::CANCELLED->value,
                VisitationStatus::RESCHEDULE->value
            ])) {
                return response()->json([
                    'message' => "You cannot update the record because the visitation has already been {$visitation->status->value}.",
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($visitation->status != VisitationStatus::ACCEPTED->value) {
                return response()->json([
                    'message' => 'You need to accept the visitation request before adding the consultation report.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Update the visitation details
            $visitation->title = $validated['reason'];
            $visitation->description = $validated['description'];
            $visitation->save();

            return response()->json([
                'message' => 'Visitation Rescheduled Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function addRecommendedTests(string $id, Request $request)
    // {
    //     $validated = $request->validate([
    //         'reason' => 'required|string|max:255',
    //         'description' => 'required|string',
    //     ]);

    //     try {
    //         $visitation = Visitation::with('patient')->find($id);

    //         if (!$visitation) {
    //             return response()->json([
    //                 'message' => 'The visitation detail not found',
    //                 'status' => 'success',
    //                 'success' => true,
    //             ], 400);
    //         }

    //         if (in_array($visitation->status, [
    //             VisitationStatus::CONSULTED->value,
    //             VisitationStatus::CANCELLED->value,
    //             VisitationStatus::RESCHEDULE->value
    //         ])) {
    //             return response()->json([
    //                 'message' => "You cannot update the record because the visitation has already been {$visitation->status->value}.",
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         if ($visitation->status != VisitationStatus::ACCEPTED->value) {
    //             return response()->json([
    //                 'message' => 'You need to accept the visitation request before adding the consultation report.',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // Update the visitation details
    //         $visitation->title = $validated['reason'];
    //         $visitation->description = $validated['description'];
    //         $visitation->save();

    //         return response()->json([
    //             'message' => 'Visitation Rescheduled Successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error('Unexpected error: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function update(Request $request, $id): JsonResponse
    {
        $validated = $request->validate(
            [
                'patient' => ['required', 'integer'],
                'assigned_doctor' => ['required', 'integer'],
                'payment_reference' => ['required', 'string'],
                'date' => ['required', 'date_format:Y-m-d'],
                'time' => ['required', 'regex:/^([01][0-9]|2[0-3]):[0-5][0-9]$/'],
            ],
            [
                'patient.required' => 'Patient detail is required',
                'patient.integer' => 'Patient detail contains invalid data',
                'assigned_doctor.required' => 'Assigned doctor detail is required',
                'assigned_doctor.integer' => 'Assigned doctor detail contains invalid data',
                'payment_reference.required' => 'Payment reference detail is required',
                'date.required' => 'Date is required',
                'date.date_format' => 'Date contains invalid data',
                'time.required' => 'Time is required',
                'time.regex' => 'Time is invalid'
            ]
        );

        try {
            $staff = Auth::user();

            $patient = Patient::where('id', $validated['patient'])->first();
            if (!$patient) {
                throw new BadRequestHttpException('Patient detail not found');
            }

            $doctor = User::where('id', $validated['assigned_doctor'])->first();
            if (!$doctor) {
                throw new BadRequestHttpException('Assigned doctor detail not found');
            }

            $visitation = Visitation::where('patient_id', $patient->id)->where('id', $id)->first();
            if (!$visitation) {
                return response()->json([
                    'message' => 'Something went wrong. Try again in 5 minutes',
                    'status' => 'error',
                    'success' => false,
                ], 500);
            }

            $overlappingVisitations = Visitation::where('start_date', $validated['date'])
                ->where('start_time', $validated['time'])
                ->where('id', '!=', $id)
                ->exists();

            if ($overlappingVisitations) {
                throw new BadRequestHttpException('The visitation overlaps with others');
            }

            $patientTodayVisitations = $this->findNumberOfVisitationsForPatient($patient->id, $validated['date']);
            if ($patientTodayVisitations->count() > 1) {
                throw new BadRequestHttpException('Patient cannot book a visitation more than twice per day');
            }

            Log::info($validated['payment_reference']);

            $payment = Payment::with('service')
                ->where('transaction_reference', $validated['payment_reference'])
                ->first();

            if (!$payment) {
                throw new BadRequestHttpException('Payment detail not found');
            }

            if (!$payment->status == PaymentStatus::COMPLETED->value) {
                throw new BadRequestHttpException('You need to make a payment before you can continue');
            }

            $endTime = Carbon::createFromFormat('H:i', $validated['time'])->addMinutes(20)->format('H:i');

            $visitation->end_time = $endTime;
            $visitation->start_date = $validated['date'];
            $visitation->start_time = $validated['time'];
            $visitation->history = json_encode([
                [
                    'title' => VisitationStatus::PENDING,
                    'date' => now(),
                    'created_by' => $staff->fullname,
                    'staff_detail' => $staff->staff_id,
                ],
                [
                    'title' => VisitationStatus::PENDING,
                    'date' => now(),
                    'created_by' => $staff->fullname,
                    'staff_detail' => $staff->staff_id,
                ]
            ]);
            $visitation->assigned_doctor_id = $doctor->id;
            $visitation->patient()->associate($patient);
            $visitation->lastUpdatedBy()->associate($staff);
            $visitation->save();

            return response()->json([
                'message' => 'Visitation detail updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::error('Error creating visitation: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again');
        }
    }

    public function delete($id)
    {
        try {
            $visitation = Visitation::find($id);

            if (!$visitation) {
                throw new BadRequestHttpException('The Visitation detail not found');
            }

            $visitation->delete();

            return response()->json([
                'message' => 'Visitation deleted successfully.',
                'status' => 'success',
                'success' => true
            ], 200);
        } catch (BadRequestHttpException $e) {
            Log::info('Error accepting Visitation: ' . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            Log::info('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function createRecommendedTests(Request $request, $id)
    // {
    //     $recommendedTestsDto = $request->validate([
    //         'recommended_tests' => 'required|array|min:1',
    //         'recommended_tests.*' => 'required'
    //     ], [
    //         'recommended_tests.required' => 'The recommended tests field is required.',
    //         'recommended_tests.array' => 'The recommended tests must be an array.',
    //         'recommended_tests.min' => 'At least one test must be provided.',
    //         'recommended_tests.*.required' => 'Test entry cannot be empty.'
    //     ]);

    //     try {
    //         $staff = Auth::user();
    //         $visitation = Visitation::find($id);

    //         if (!$visitation) {
    //             throw new BadRequestException('Visitation detail not found');
    //         }

    //         $tests = [];
    //         $notFoundTests = [];

    //         foreach ($recommendedTestsDto['recommended_tests'] as $testId) {
    //             $test = Service::find($testId);
    //             if ($test) {
    //                 $tests[] = $test->id;
    //             } else {
    //                 $notFoundTests[] = $testId;
    //             }
    //         }

    //         Log::info($notFoundTests);

    //         $visitation->availableTests()->sync($tests);
    //         $visitation->not_available_tests = $notFoundTests;
    //         $visitation->last_updated_by_id = $staff->id;
    //         $visitation->save();

    //         return [
    //             'message' => 'Visitation detail updated successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ];
    //     } catch (BadRequestHttpException $e) {
    //         Log::info('Error accepting Visitation: ' . $e->getMessage());
    //         throw $e;
    //     } catch (Exception $e) {
    //         Log::info('Unexpected error: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }


    // public function createRecommendedTests(Request $request, $visitationId)
    // {
    //     $validated = $request->validate([
    //         'treatment_id' => 'required|exists:treatments,id',
    //         'recommended_tests' => 'required|array|min:1',
    //         'recommended_tests.*' => 'required|exists:services,id',
    //     ], [
    //         'treatment_id.required' => 'A treatment must be selected.',
    //         'treatment_id.exists' => 'The selected treatment does not exist.',
    //         'recommended_tests.required' => 'The recommended tests field is required.',
    //         'recommended_tests.array' => 'The recommended tests must be an array.',
    //         'recommended_tests.min' => 'At least one test must be provided.',
    //         'recommended_tests.*.required' => 'Test entry cannot be empty.',
    //         'recommended_tests.*.exists' => 'One or more tests are invalid.',
    //     ]);

    //     try {
    //         $staff = Auth::user();

    //         $visitation = Visitation::with('patient')->find($visitationId);
    //         if (!$visitation) {
    //             throw new BadRequestHttpException('Visitation detail not found.');
    //         }

    //         Log::info($visitation->patient->id);

    //         if (in_array($visitation->status, [
    //             VisitationStatus::CONSULTED->value,
    //             VisitationStatus::CANCELLED->value,
    //             VisitationStatus::RESCHEDULE->value
    //         ])) {
    //             return response()->json([
    //                 'message' => "You cannot add the tests because the visitation has already been {$visitation->status->value}.",
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $treatment = Treatment::find($validated['treatment_id']);
    //         if (!$treatment || $treatment->visitation_id != $visitation->id) {
    //             throw new BadRequestHttpException('The selected treatment does not belong to this visitation.');
    //         }

    //         $tests = [];
    //         $notFoundTests = [];

    //         foreach ($validated['recommended_tests'] as $testId) {
    //             $test = Service::find($testId);
    //             if ($test) {
    //                 $tests[] = $test;
    //             } else {
    //                 $notFoundTests[] = $testId;
    //             }
    //         }

    //         if (count($notFoundTests) > 0) {
    //             return response()->json([
    //                 'message' => 'Some recommended tests were not found.',
    //                 'status' => 'error',
    //                 'success' => false,
    //                 'not_found_tests' => $notFoundTests,
    //             ], 400);
    //         }

    //         DB::beginTransaction();

    //         $requests = [];

    //         foreach ($tests as $test) {
    //             $labRequest = LabRequest::create([
    //                 'patient_id' => $visitation->patient->id,
    //                 'is_patient' => true,
    //                 'service_id' => $test->id,
    //                 'priority' => 'URGENT',
    //                 'is_approval_required' => false,
    //                 'added_by_id' => $staff->id,
    //                 'request_date' => Carbon::now(),
    //                 'customer_name' => $visitation->patient->fullname,
    //                 'treatment_id' => $treatment->id,
    //             ]);

    //             $payment = new Payment();
    //             $payment->amount = number_format($test->price ?? 0, 3, '.', '');
    //             $payment->amount_payable = round(number_format($test->price ?? 0, 3, '.', ''));
    //             $payment->customer_name = $visitation->patient->fullname;
    //             $payment->transaction_reference = strtoupper(Str::random(10));
    //             $payment->patient_id = $visitation->patient->id ?? null;
    //             $payment->payable_id = $labRequest->id;
    //             $payment->payable_type = LabRequest::class;
    //             $payment->added_by_id = $staff->id;
    //             $payment->type = 'LAB-TEST';
    //             $payment->history = [
    //                 ['date' => now(), 'title' => 'CREATED'],
    //             ];
    //             $payment->last_updated_by_id = $staff->id;
    //             $payment->save();

    //             $requests[] = $labRequest;
    //         }


    //         $visitation->last_updated_by_id = $staff->id;
    //         $visitation->save();
    //         $visitation->recommendedTests()->sync($requests);

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Recommended tests added successfully for the treatment.',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (BadRequestHttpException $e) {
    //         Log::info('Error recommending tests: ' . $e->getMessage());
    //         throw $e;
    //     } catch (Exception $e) {
    //         Log::error('Unexpected error: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again later.',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function createRecommendedTests(Request $request, $id)
    {
        $validated = $request->validate([
            'treatment_id' => 'required|exists:treatments,id',
            'recommended_tests' => 'required|array|min:1',
            'recommended_tests.*' => 'required|exists:services,id',
        ], [
            'treatment_id.required' => 'A treatment must be selected.',
            'treatment_id.exists' => 'The selected treatment does not exist.',
            'recommended_tests.required' => 'The recommended tests field is required.',
            'recommended_tests.array' => 'The recommended tests must be an array.',
            'recommended_tests.min' => 'At least one test must be provided.',
            'recommended_tests.*.required' => 'Test entry cannot be empty.',
            'recommended_tests.*.exists' => 'One or more tests are invalid.',
        ]);

        try {
            $staff = Auth::user();

            // Load treatment and determine which parent it belongs to
            $treatment = Treatment::with(['visitation.patient', 'admission.patient'])->find($validated['treatment_id']);

            if (!$treatment) {
                throw new BadRequestHttpException('Treatment not found.');
            }

            // Determine context by matching the provided $id to treatment's visitation_id or admission_id
            $isVisitation = $treatment->visitation_id && (string)$treatment->visitation_id === (string)$id;
            $isAdmission  = $treatment->admission_id  && (string)$treatment->admission_id  === (string)$id;

            if (!$isVisitation && !$isAdmission) {
                throw new BadRequestHttpException('The selected treatment does not belong to the provided visitation/admission id.');
            }

            // Resolve context model and patient
            $context = null;
            $patient = null;
            if ($isVisitation) {
                $context = $treatment->visitation; // eager loaded relation
                $patient = $context->patient;
                // Status restrictions apply only for visitations
                if (in_array($context->status, [
                    VisitationStatus::CONSULTED->value,
                    VisitationStatus::CANCELLED->value,
                    VisitationStatus::RESCHEDULE->value
                ])) {
                    return response()->json([
                        'message' => "You cannot add tests because this visitation has already been {$context->status->value}.",
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            } else {
                $context = $treatment->admission; // eager loaded relation
                $patient = $context->patient;
            }

            if (!$patient) {
                throw new BadRequestHttpException('Patient for the selected context not found.');
            }

            // Validate tests exist
            $tests = Service::whereIn('id', $validated['recommended_tests'])->get();
            $foundIds = $tests->pluck('id')->all();
            $notFound = array_values(array_diff($validated['recommended_tests'], $foundIds));
            if (count($notFound) > 0) {
                return response()->json([
                    'message' => 'Some recommended tests were not found.',
                    'status' => 'error',
                    'success' => false,
                    'not_found_tests' => $notFound,
                ], 400);
            }

            DB::beginTransaction();

            $requests = [];
            foreach ($tests as $test) {
                $labRequest = LabRequest::create([
                    'patient_id' => $patient->id,
                    'is_patient' => true,
                    'service_id' => $test->id,
                    'priority' => 'URGENT',
                    'is_approval_required' => false,
                    'added_by_id' => $staff->id,
                    'request_date' => Carbon::now(),
                    'customer_name' => $patient->fullname,
                    'treatment_id' => $treatment->id,
                ]);

                $payment = new Payment();
                $payment->amount = number_format($test->price ?? 0, 3, '.', '');
                $payment->amount_payable = round(number_format($test->price ?? 0, 3, '.', ''));
                $payment->customer_name = $patient->fullname;
                $payment->transaction_reference = strtoupper(Str::random(10));
                $payment->patient_id = $patient->id ?? null;
                $payment->payable_id = $labRequest->id;
                $payment->payable_type = LabRequest::class;
                $payment->added_by_id = $staff->id;
                $payment->type = 'LAB-TEST';
                $payment->history = [
                    ['date' => now(), 'title' => 'CREATED'],
                ];
                $payment->last_updated_by_id = $staff->id;
                $payment->save();

                $requests[] = $labRequest;
            }

            // update context metadata and sync relation
            $context->last_updated_by_id = $staff->id;
            $context->save();

            // If recommendedTests relation expects ids, pass ids; if it expects models, pass models.
            // Adjust this line to match your relationship signature.
            $context->recommendedTests()->sync(array_map(fn($r) => $r->id, $requests));

            DB::commit();

            return response()->json([
                'message' => 'Recommended tests added successfully.',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::info('Error recommending tests: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again later.',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }



    // public function updateRecommendedTests(Request $request, $visitationId)
    // {
    //     $validated = $request->validate([
    //         'treatment_id' => 'required|exists:treatments,id',
    //         'recommended_tests' => 'required|array|min:1',
    //         'recommended_tests.*' => 'required|exists:services,id',
    //     ]);

    //     try {
    //         $staff = Auth::user();

    //         $visitation = Visitation::with('patient')->find($visitationId);
    //         if (!$visitation) {
    //             throw new BadRequestHttpException('Visitation detail not found.');
    //         }

    //         $treatment = Treatment::find($validated['treatment_id']);
    //         if (!$treatment || $treatment->visitation_id != $visitation->id) {
    //             throw new BadRequestHttpException('The selected treatment does not belong to this visitation.');
    //         }

    //         $newTestIds = collect($validated['recommended_tests'])->map(fn($id) => (int)$id)->unique()->values();
    //         $existingRequests = LabRequest::where('treatment_id', $treatment->id)->get();

    //         $existingTestIds = $existingRequests->pluck('service_id');
    //         $existingPaidTestIds = collect();

    //         // Identify paid tests
    //         foreach ($existingRequests as $request) {
    //             $payment = Payment::where('request_id', $request->id)
    //                 ->where('service_id', $request->service_id)
    //                 ->where('status', 'PAID')
    //                 ->first();

    //             if ($payment) {
    //                 $existingPaidTestIds->push($request->service_id);
    //             }
    //         }

    //         // Don't allow deletion of paid tests
    //         $requestedToRemove = $existingTestIds->diff($newTestIds);
    //         $removableTests = $requestedToRemove->diff($existingPaidTestIds);

    //         if ($requestedToRemove->intersect($existingPaidTestIds)->isNotEmpty()) {
    //             return response()->json([
    //                 'message' => 'You cannot remove tests that have already been paid for.',
    //                 'status' => 'error',
    //                 'success' => false,
    //                 'paid_tests' => $existingPaidTestIds->intersect($requestedToRemove)->values(),
    //             ], 400);
    //         }

    //         DB::beginTransaction();

    //         // Delete lab requests & payments for removable tests
    //         foreach ($removableTests as $serviceId) {
    //             $labRequest = $existingRequests->firstWhere('service_id', $serviceId);
    //             if ($labRequest) {
    //                 Payment::where('request_id', $labRequest->id)->delete();
    //                 $labRequest->delete();
    //             }
    //         }

    //         // Add newly added tests
    //         $testsToAdd = $newTestIds->diff($existingTestIds);
    //         foreach ($testsToAdd as $serviceId) {
    //             $test = Service::find($serviceId);
    //             if (!$test) continue;

    //             $labRequest = LabRequest::create([
    //                 'patient_id' => $visitation->patient->id,
    //                 'is_patient' => true,
    //                 'service_id' => $test->id,
    //                 'priority' => 'URGENT',
    //                 'is_approval_required' => false,
    //                 'added_by_id' => $staff->id,
    //                 'request_date' => now(),
    //                 'customer_name' => $visitation->patient->fullname,
    //                 'treatment_id' => $treatment->id,
    //             ]);

    //             Payment::create([
    //                 'amount' => number_format($test->price ?? 0, 3, '.', ''),
    //                 'amount_payable' => number_format($test->price ?? 0, 3, '.', ''),
    //                 'transaction_reference' => strtoupper(Str::random(10)),
    //                 'customer_name' => $visitation->patient->fullname,
    //                 'status' => 'CREATED',
    //                 'type' => "LAB-TEST",
    //                 'patient_id' => $visitation->patient->id,
    //                 'service_id' => $test->id,
    //                 'added_by_id' => $staff->id,
    //                 'treatment_id' => $treatment->id,
    //                 'request_id' => $labRequest->id
    //             ]);
    //         }

    //         $visitation->last_updated_by_id = $staff->id;
    //         $visitation->save();

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Recommended tests updated successfully.',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (BadRequestHttpException $e) {
    //         Log::info('Error updating tests: ' . $e->getMessage());
    //         throw $e;
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Log::error('Unexpected error: ' . $e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again later.',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }


    private function findOverlapVisitations(string $date, string $startTime, ?string $id = null)
    {
        $query = Visitation::where('start_date', $date)
            ->where('start_time', '<=', $startTime)
            ->where('end_time', '>=', $startTime);

        if ($id) {
            $query->where('id', '!=', $id);
        }

        return $query->get();
    }

    private function findNumberOfVisitationsForPatient(string $patientId, string $date, ?string $id = null)
    {
        $query = Visitation::whereHas('patient', function ($query) use ($patientId) {
            $query->where('id', $patientId);
        })->where('start_date', $date);

        if ($id) {
            $query->where('id', '!=', $id);
        }

        return $query->get();
    }
}
