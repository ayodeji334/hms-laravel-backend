<?php

namespace App\Http\Controllers;

use App\Enums\AdmissionStatus;
use App\Enums\PaymentStatus;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\DoctorReport;
use App\Models\DrugAdministrationChart;
use App\Models\FluidBalanceChart;
use App\Models\Note;
use App\Models\NurseReport;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AdmissionController extends Controller
{
    protected $vitalSignController;
    public function __construct(VitalSignController $vitalSignController)
    {
        $this->vitalSignController = $vitalSignController;
    }

    public function checkIfPatientIsAdmitted(string $patientId)
    {
        try {
            $admission = Admission::whereHas('patient', function ($query) use ($patientId) {
                $query->where('id', $patientId);
            })->where('status', 'ADMITTED')->first();

            return $admission;
        } catch (Exception $e) {
            Log::error('Error checking if patient is admitted: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'admitted_by' => 'required|exists:users,id',
            'patient_id' => 'required|exists:patients,id',
            'bed_id' => 'required|exists:beds,id',
            'notes' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'admission_date' => 'required|date',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $staffId = Auth::user()->id;

                $doctor = User::find($request->admitted_by);
                if (!$doctor) {
                    response()->json([
                        'message' => "The doctor's detail not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $patient = Patient::find($request->patient_id);
                if (!$patient) {
                    response()->json([
                        'message' => 'The patient account detail not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $bed = Bed::find($request->bed_id);
                if (!$bed) {
                    response()->json([
                        'message' => 'The bed detail not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                if ($bed->status !== 'AVAILABLE') {
                    response()->json([
                        'message' => 'The selected bed is not available',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $existingAdmission = Admission::where('patient_id', $patient->id)
                    ->where('status', 'ADMITTED')
                    ->first();

                if ($existingAdmission) {
                    return response()->json([
                        'message' => 'Selected patient already admitted',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // Create the new Admission record
                $admission = new Admission();
                $admission->added_by_id = $staffId;
                $admission->admitted_by_id = $doctor->id;
                $admission->patient_id = $patient->id;
                $admission->bed_id = $bed->id;
                $admission->notes = $request->notes;
                $admission->diagnosis = $request->diagnosis;
                $admission->admission_date = $request->admission_date;
                $admission->last_updated_by_id = $staffId;
                $admission->save();

                // Mark the bed as occupied
                $bed->status = 'OCCUPIED';
                $bed->assigned_patient_id = $patient->id;
                $bed->save();

                $patient->is_admitted = true;
                $patient->save();

                return response()->json([
                    'message' => 'Admission Record Created successfully',
                    'success' => true,
                    'status' => 'success',
                ], 201);
            });
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => "Something went wrong. Try again in 5 minutes",
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'admitted_by' => 'required|exists:users,id',
            'patient_id' => 'required|exists:patients,id',
            'bed_id' => 'required|exists:beds,id',
            'notes' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'admission_date' => 'required|date',
        ]);

        try {
            $admission = Admission::findOrFail($id);

            // Check if patient already has an active admission different from this record
            $existingAdmission = Admission::where('patient_id', $validated['patient_id'])
                ->where('status', 'ADMITTED')
                ->where('id', '!=', $id)
                ->first();

            if ($existingAdmission) {
                return response()->json([
                    'message' => 'Selected patient is already admitted',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            return DB::transaction(function () use ($validated, $admission) {
                $staffId = Auth::id();

                // Check doctor existence
                $doctor = User::find($validated['admitted_by']);
                if (!$doctor) {
                    return response()->json([
                        'message' => "Doctor not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // Check patient existence
                $patient = Patient::find($validated['patient_id']);
                if (!$patient) {
                    return response()->json([
                        'message' => "Patient not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // Check bed existence
                $bed = Bed::find($validated['bed_id']);
                if (!$bed) {
                    return response()->json([
                        'message' => "Bed not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // Prevent assigning an unavailable bed, unless it's already assigned to this admission
                if ($bed->status !== 'AVAILABLE' && $bed->id !== $admission->bed_id) {
                    return response()->json([
                        'message' => 'Selected bed is not available',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // Free previous bed if changed
                if ($admission->bed_id !== $bed->id) {
                    $previousBed = Bed::find($admission->bed_id);
                    if ($previousBed) {
                        $previousBed->status = 'AVAILABLE';
                        $previousBed->assigned_patient_id = null;
                        $previousBed->save();
                    }
                }

                // Update the admission record
                $admission->update([
                    'added_by_id' => $staffId,
                    'admitted_by_id' => $doctor->id,
                    'patient_id' => $patient->id,
                    'bed_id' => $bed->id,
                    'notes' => $validated['notes'] ?? null,
                    'diagnosis' => $validated['diagnosis'] ?? null,
                    'admission_date' => $validated['admission_date'],
                    'last_updated_by_id' => $staffId,
                ]);

                // Update bed to occupied
                $bed->status = 'OCCUPIED';
                $bed->assigned_patient_id = $patient->id;
                $bed->save();

                return response()->json([
                    'message' => 'Admission record updated successfully',
                    'success' => true,
                    'status' => 'success',
                ], 200);
            });
        } catch (Exception $e) {
            Log::error('Admission update failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);

            $data = Admission::with([
                'addedBy:id,firstname,lastname',
                'patient:id,firstname,lastname,patient_reg_no',
                'admittedBy:id,firstname,lastname',
                'lastUpdatedBy:id,firstname,lastname',
                'bed:id,status,room_id',
                'bed.room:id,room_category_id',
                'bed.room.category:id,name',
            ])
                ->when($request->input('status') && $request->input('status') !== 'ALL', function ($query) use ($request) {
                    $query->where('status', $request->input('status'));
                })
                ->when($request->input('q'), function ($query) use ($request) {
                    $search = $request->input('q');
                    $query->whereHas('patient', function ($patientQuery) use ($search) {
                        $patientQuery->where('firstname', 'LIKE', "%{$search}%")
                            ->orWhere('lastname', 'LIKE', "%{$search}%")
                            ->orWhere('patient_id', $search);
                    });
                })
                ->orderBy('updated_at', 'DESC')
                ->paginate($limit);

            return response()->json([
                'message' => 'Admission records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => "Something went wrong. Try again in 5 minutes",
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $staff = Auth::user();

            $admission = Admission::find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $admission->last_deleted_by_id = $staff->id;
            $admission->save();
            $admission->delete();

            return response()->json([
                'message' => 'Admission record deleted successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $admission = Admission::with([
                'patient.wallet',
                'patient.organisationHmo:id,name,type,phone_number',
                'investigations.createdBy:id,firstname,lastname,role',
                'investigations:id,content,title,noteable_type,noteable_id,created_by_id',
                'nurseReports.createdBy',
                'doctorReports.createdBy',
                'nurseReports.vitalSign',
                'fluidBalanceCharts.addedBy',
                'drugAdministrationCharts.addedBy',
                'admittedBy',
                'lastUpdatedBy',
                'addedBy',
                'treatments',
                'recommendedTests.service',
                'recommendedTests.testResult',
                'bed.room.category',
                'prescriptions',
                'prescriptions.requestedBy',
                'prescriptions.items',
                'prescriptions.items.product',
                'prescriptions.notes' => function ($query) {
                    $query->limit(1);
                }
            ])->find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // If discharged, no need to recalculate
            // if ($admission->status === 'DISCHARGED') {
            //     $wallet = $admission->patient?->wallet;
            //     return response()->json([
            //         'message' => 'Admission record fetched successfully',
            //         'status' => 'success',
            //         'success' => true,
            //         'data' => array_merge(
            //             $admission->toArray(),
            //             [
            //                 'financial_breakdown' => [
            //                     'deposit_balance' => $wallet->deposit_balance ?? 0,
            //                     'outstanding_balance' => $wallet->outstanding_balance ?? 0,
            //                 ]
            //             ]
            //         )
            //     ], 200);
            // }

            // Get live financial summary for active admission
            $summary = $this->getFinancialSummary($admission->id);

            return response()->json([
                'message' => 'Admission record fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => array_merge($admission->toArray(), [
                    'financial_breakdown' => $summary,
                ]),
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    private function getFinancialSummary($admissionId)
    {
        try {
            $admission = Admission::with('patient.wallet')->findOrFail($admissionId);

            $patientId = $admission->patient_id;
            $wallet = $admission->patient?->wallet;
            $now = now();

            // Calculate days spent
            $admissionDate = Carbon::parse($admission->admission_date);
            $daysSpent = (int) $admissionDate->diffInDays($now) ?: 1;

            // Bed cost
            $bedService = Service::where('name', 'bed space')->first();
            $bedPrice = $bedService?->price ?? 500;
            $bedCost = $bedPrice * $daysSpent;

            // Fetch treatment
            $treatments = DB::table('treatments')
                ->where('admission_id', $admissionId)
                ->orderBy('created_at', 'asc')
                ->get();

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
                ->where('payable_type', Admission::class)
                ->where('payable_id', $admissionId)
                ->where('status', 'COMPLETED')
                ->select('id', 'amount', 'created_at as payment_date', 'transaction_reference')
                ->orderBy('created_at', 'asc')
                ->get();

            // Bed record
            $balance += $bedCost;
            $records[] = [
                'date' => $admission->admission_date ? Carbon::parse($admission->admission_date)->format('Y-m-d') : null,
                'qty' => $daysSpent,
                'particular' => 'Bed Space (' . $daysSpent . ' days)',
                'charges' => $bedCost,
                'credit' => 0,
                'balance' => $balance,
            ];

            // Add payment records
            foreach ($payments as $pay) {
                $balance -= $pay->amount;
                $records[] = [
                    'date' => $pay->payment_date ? Carbon::parse($pay->payment_date)->format('Y-m-d') : null,
                    'qty' => '',
                    'particular' => 'Payment (Ref: ' . $pay->transaction_reference . ')',
                    'charges' => 0,
                    'credit' => $pay->amount,
                    'balance' => $balance,
                ];
            }

            // Totals
            $totalCharges = $bedCost + $treatmentCost;
            $totalPayments = $payments->sum('amount');
            $balanceDue = max(0, $totalCharges - $totalPayments);
            $depositBalance = $wallet?->deposit_balance ?? 0;

            return [
                'admission' => [
                    'id' => $admission->id,
                    'patient_id' => $patientId,
                    'admission_date' => $admission->admission_date,
                    'discharge_date' => $admission->discharge_date,
                    'days_spent' => $daysSpent,
                ],
                'summary' => [
                    'records' => $records,
                    'bed_cost' => (int) $bedCost,
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

    public function addFluidBalanceChart(Request $request, $id)
    {
        $request->validate([
            'input_iv_volume' => 'required|numeric',
            'input_oral_volume' => 'required|numeric',
            'input_tube_volume' => 'required|numeric',
            'input_type' => 'required|string',
            'output_faeces_volume' => 'required|numeric',
            'output_urine_volume' => 'required|numeric',
            'output_vomit_volume' => 'required|numeric',
            'output_type' => 'required|string',
            'time' => 'required|string',
        ]);

        try {
            $staff = Auth::user();

            $admission = Admission::find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($admission->status === AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Cannot complete the process, because the patient has been discharged',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $fluidBalanceChart = new FluidBalanceChart();
            $fluidBalanceChart->added_by_id = $staff->id;
            $fluidBalanceChart->last_updated_by_id = $staff->id;
            $fluidBalanceChart->input_iv_volume = $request->input_iv_volume;
            $fluidBalanceChart->input_oral_volume = $request->input_oral_volume;
            $fluidBalanceChart->input_tube_volume = $request->input_tube_volume;
            $fluidBalanceChart->input_type = $request->input_type;
            $fluidBalanceChart->input_total = $request->input_iv_volume + $request->input_oral_volume + $request->input_tube_volume;
            $fluidBalanceChart->output_faeces_volume = $request->output_faeces_volume;
            $fluidBalanceChart->output_urine_volume = $request->output_urine_volume;
            $fluidBalanceChart->output_vomit_volume = $request->output_vomit_volume;
            $fluidBalanceChart->output_type = $request->output_type;
            $fluidBalanceChart->output_total = $request->output_faeces_volume + $request->output_urine_volume + $request->output_vomit_volume;
            $fluidBalanceChart->time = $request->time;
            $fluidBalanceChart->admission_id = $admission->id;
            $fluidBalanceChart->save();

            return response()->json([
                'message' => 'Fluid Balance Record created successfully',
                'status' => 'success',
                'success' => true,
            ], 201);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateFluidBalanceChart(Request $request, $id)
    {
        $request->validate([
            'input_iv_volume' => 'required|numeric',
            'input_oral_volume' => 'required|numeric',
            'input_tube_volume' => 'required|numeric',
            'input_type' => 'required|string',
            'output_faeces_volume' => 'required|numeric',
            'output_urine_volume' => 'required|numeric',
            'output_vomit_volume' => 'required|numeric',
            'output_type' => 'required|string',
            'time' => 'required|string',
        ]);

        try {
            $staff = Auth::user();
            $fluidBalanceChart = FluidBalanceChart::withTrashed()->find($id);

            if (!$fluidBalanceChart) {
                return response()->json([
                    'message' => 'Fluid Balance Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($fluidBalanceChart->added_by_id !== $staff->id) {
                return response()->json([
                    'message' => 'You are not authorized to update the record',
                    'status' => 'error',
                    'success' => false,
                ], 403);
            }

            $fluidBalanceChart->last_updated_by_id = $staff->id;
            $fluidBalanceChart->input_iv_volume = $request->input_iv_volume;
            $fluidBalanceChart->input_oral_volume = $request->input_oral_volume;
            $fluidBalanceChart->input_tube_volume = $request->input_tube_volume;
            $fluidBalanceChart->input_type = $request->input_type;
            $fluidBalanceChart->input_total = $request->input_iv_volume + $request->input_oral_volume + $request->input_tube_volume;
            $fluidBalanceChart->output_faeces_volume = $request->output_faeces_volume;
            $fluidBalanceChart->output_urine_volume = $request->output_urine_volume;
            $fluidBalanceChart->output_vomit_volume = $request->output_vomit_volume;
            $fluidBalanceChart->output_type = $request->output_type;
            $fluidBalanceChart->output_total = $request->output_faeces_volume + $request->output_urine_volume + $request->output_vomit_volume;
            $fluidBalanceChart->time = $request->time;
            $fluidBalanceChart->save();

            return response()->json([
                'message' => 'Fluid Balance Record updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function deleteFluidBalanceChart($id)
    {
        try {
            $staff = Auth::user();

            $fluidBalanceChart = FluidBalanceChart::withTrashed()->find($id);

            if (!$fluidBalanceChart) {
                return response()->json([
                    'message' => 'Fluid Balance Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($fluidBalanceChart->added_by_id !== $staff->id) {
                return response()->json([
                    'message' => 'You are not authorized to delete the record',
                    'status' => 'error',
                    'success' => false,
                ], 403);
            }

            $fluidBalanceChart->delete();

            return response()->json([
                'message' => 'Fluid Balance Record deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function addDrugAdministrationChart(Request $request, $id)
    {
        $request->validate([
            'procedure' => 'required|string',
            'time' => 'required|string',
            'dosage' => 'required|string',
            'day' => 'required|string',
            'date' => 'required|string'
        ]);

        try {
            $staff = Auth::user();

            $admission = Admission::find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($admission->status === AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Cannot complete the process, because the patient has been discharged',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $drugAdministrationChart = new DrugAdministrationChart();
            $drugAdministrationChart->added_by_id = $staff->id;
            $drugAdministrationChart->last_updated_by_id = $staff->id;
            $drugAdministrationChart->procedure = $request->procedure;
            $drugAdministrationChart->time = $request->time;
            $drugAdministrationChart->dosage = $request->dosage;
            $drugAdministrationChart->day = $request->day;
            $drugAdministrationChart->date = $request->date;
            $drugAdministrationChart->admission_id = $admission->id;
            $drugAdministrationChart->save();

            return response()->json([
                'message' => 'Drug Adminstration Chart created successfully',
                'status' => 'success',
                'success' => true,
            ], 201);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateDrugAdministrationChart(Request $request, $id)
    {
        $request->validate([
            'prodecure' => 'required|string',
            'time' => 'required|time',
            'dosage' => 'required|string',
            'day' => 'required|string',
            'date' => 'required|date'
        ]);

        try {
            $staff = Auth::user();
            $drugAdministrationChart = DrugAdministrationChart::withTrashed()->find($id);

            if (!$drugAdministrationChart) {
                return response()->json([
                    'message' => 'Drug Adminstration Chart Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($drugAdministrationChart->added_by_id !== $staff->id) {
                return response()->json([
                    'message' => 'You are not authorized to update the record',
                    'status' => 'error',
                    'success' => false,
                ], 403);
            }

            $drugAdministrationChart->added_by_id = $staff->id;
            $drugAdministrationChart->last_updated_by_id = $staff->id;
            $drugAdministrationChart->procedure = $request->procedure;
            $drugAdministrationChart->time = $request->time;
            $drugAdministrationChart->dosage = $request->dosage;
            $drugAdministrationChart->day = $request->day;
            $drugAdministrationChart->date = $request->date;
            $drugAdministrationChart->save();

            return response()->json([
                'message' => 'Drug Adminstration Chart updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function deleteDrugAdministrationChart($id)
    {
        try {
            $staff = Auth::user();

            $drugAdministrationChart = DrugAdministrationChart::withTrashed()->find($id);

            if (!$drugAdministrationChart) {
                return response()->json([
                    'message' => 'Drug Adminstration Chart Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($drugAdministrationChart->added_by_id !== $staff->id) {
                return response()->json([
                    'message' => 'You are not authorized to delete the record',
                    'status' => 'error',
                    'success' => false,
                ], 403);
            }

            $drugAdministrationChart->delete();

            return response()->json([
                'message' => 'Drug Adminstration Chart Record deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function addNurseReport(Request $request, $id)
    {
        $createNurseReportDto = $request->validate([
            'blood_pressure' => 'required|string',
            'heart_rate' => 'required|integer',
            'respiratory_rate' => 'required|integer',
            'temperature' => 'required|integer',
            'remark' => 'nullable|string',
        ]);

        try {
            $staff = Auth::user();

            $admission = Admission::with('patient')->find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($admission->status === AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Cannot complete the process, because the patient has been discharged',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            DB::beginTransaction();

            $vitaSign = $this->vitalSignController->createAdmissionVitalSign($createNurseReportDto, $admission->patient_id);

            Log::info($vitaSign);

            $nurseReport = new NurseReport();
            $nurseReport->admission_id = $admission->id;
            $nurseReport->remark = $request->remark;
            $nurseReport->vital_sign_id = $vitaSign->id;
            $nurseReport->created_by_id = $staff->id;
            $nurseReport->save();

            DB::commit();

            return response()->json([
                'message' => 'Nurse Report added successfully',
                'statua' => 'success',
                'success' => true
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info("Nooo" . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateNurseReport(Request $request, $id)
    {
        $updateNursereportDto = $request->validate([
            'blood_pressure' => 'required|string',
            'heart_rate' => 'required|integer',
            'respiratory_rate' => 'required|integer',
            'temperature' => 'required|numeric',
            'remark' => 'nullable|string',
        ]);

        try {
            $staffId = Auth::user()->id;

            $report = NurseReport::with(['createdBy', 'admission.patient', 'vitalSign'])->findOrFail($id);

            if ($report->admission->status === 'DISCHARGED') {
                return response()->json(['message' => 'Cannot update report because the patient has been discharged'], 400);
            }

            if ($report->createdBy->id !== $staffId) {
                return response()->json(['message' => 'You cannot update report created by another person'], 400);
            }

            DB::beginTransaction();

            $vitalSign = $this->vitalSignController->updateAdmissionVitalSign(
                $updateNursereportDto,
                $report->admission->patient->id,
                $report->vitalSign->id,
            );

            Log::info($vitalSign);

            $report->update([
                'remark' => $request->remark,
                'vital_sign_id' => $vitalSign->id,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Nurse Report Updated successfully',
                'success' => true,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info('noooooo' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error'
            ], 500);
        }
    }

    public function addDoctorReport(Request $request, $id)
    {
        $request->validate([
            // 'blood_pressure' => 'required|string',
            // 'heart_rate' => 'required|integer',
            // 'respiratory_rate' => 'required|integer',
            // 'temperature' => 'required|integer',
            'remark' => 'required|string',
        ]);

        try {
            $staff = Auth::user();

            $admission = Admission::with('patient')->find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'The admission detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($admission->status === AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Cannot complete the process, because the patient has been discharged',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            DB::beginTransaction();

            // $vitaSign = $this->vitalSignController->createAdmissionVitalSign($createNurseReportDto, $admission->patient_id);

            // Log::info($vitaSign);

            $nurseReport = new DoctorReport();
            $nurseReport->admission_id = $admission->id;
            $nurseReport->remark = $request->remark;
            // $nurseReport->vital_sign_id = $vitaSign->id;
            $nurseReport->created_by_id = $staff->id;
            $nurseReport->save();

            DB::commit();

            return response()->json([
                'message' => 'Doctor Report added successfully',
                'statua' => 'success',
                'success' => true
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info("Nooo" . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateDoctorReport(Request $request, $id)
    {
        $request->validate([
            // 'blood_pressure' => 'required|string',
            // 'heart_rate' => 'required|integer',
            // 'respiratory_rate' => 'required|integer',
            // 'temperature' => 'required|numeric',
            'remark' => 'required|string',
        ]);

        try {
            $staffId = Auth::user()->id;

            $report = DoctorReport::with(['createdBy', 'admission'])->findOrFail($id);

            if ($report->admission->status === 'DISCHARGED') {
                return response()->json(['message' => 'Cannot update report because the patient has been discharged'], 400);
            }

            if ($report->createdBy->id !== $staffId) {
                return response()->json(['message' => 'You cannot update report created by another person'], 400);
            }

            // $vitalSign = $this->vitalSignController->updateAdmissionVitalSign(
            //     $updateNursereportDto,
            //     $report->admission->patient->id,
            //     $report->vitalSign->id,
            // );

            // Log::info($vitalSign);

            $report->update([
                'remark' => $request->remark,
            ]);

            return response()->json([
                'message' => 'Nurse Report Updated successfully',
                'success' => true,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            Log::info('noooooo' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error'
            ], 500);
        }
    }

    public function discharge(string $id)
    {
        return $this->toggleStatus($id, "DISCHARGED");
    }

    public function readmit(string $id)
    {
        return $this->toggleStatus($id, "ADMITTED");
    }

    public function addToDebtorList(string $id)
    {
        return $this->togglePatientDebtorStatus($id, true);
    }

    public function removefromDebtorList(string $id)
    {
        return $this->togglePatientDebtorStatus($id, false);
    }

    private function togglePatientDebtorStatus(string $id, bool $status)
    {
        try {
            $staff = Auth::user();

            $admission = Admission::with([
                'patient',
                'addedBy',
                'lastUpdatedBy',
                'treatments.payments'
            ])->find($id);

            if (!$admission) {
                return response()->json([
                    'message' => 'Admission record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status == AdmissionStatus::DISCHARGED->value && $admission->status == AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Admission record already marked as discharged',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }


            if ($admission->patient->is_debtor && $status) {
                return response()->json([
                    'message' => 'Patient already added to the debtor list',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if (!$admission->patient->is_debtor && !$status) {
                return response()->json([
                    'message' => 'Patient already remove from the debtor list',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $admission->patient->is_debtor = $status;
            $admission->last_updated_by_id = $staff->id;
            $admission->save();
            $admission->patient->save();

            return response()->json([
                'message' => 'Admission status updated successfully',
                'success' => true,
                'status' => 'success',
                'data' => $admission->fresh(['patient', 'treatments.payments']),
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    private function toggleStatus(string $id, string $status)
    {
        try {
            $staff = Auth::user();

            $admission = Admission::with([
                'patient.wallet',
                'bed',
                'addedBy',
                'lastUpdatedBy',
                'treatments.payments',
                'treatments.prescriptions',
                'treatments.items.product'
            ])->find($id);

            Log::info($admission->patient->wallet);

            if (!$admission) {
                return response()->json([
                    'message' => 'Admission record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status === AdmissionStatus::DISCHARGED->value && $admission->status === AdmissionStatus::DISCHARGED->value) {
                return response()->json([
                    'message' => 'Admission record already marked as discharged',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status === AdmissionStatus::ADMITTED->value && $admission->status === AdmissionStatus::ADMITTED->value) {
                return response()->json([
                    'message' => 'Admission record already marked as admitted',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Check for unpaid treatments before discharge
            if ($status === AdmissionStatus::DISCHARGED->value && $admission->treatments->isNotEmpty()) {
                $hasUnpaid = $admission->treatments->some(function ($treatment) {
                    return $treatment->payments->some(function ($payment) {
                        return $payment->status !== PaymentStatus::COMPLETED->value;
                    });
                });

                if ($hasUnpaid) {
                    return response()->json([
                        'message' => 'Cannot discharge the patient. They have outstanding debts.',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            // Handle Discharge
            if ($status === AdmissionStatus::DISCHARGED->value) {
                $admission->discharged_by_id = $staff->id;
                $admission->discharge_date = now();

                // Free the bed if one is assigned
                if ($admission->bed) {
                    $admission->bed->status =
                        "AVAILABLE";
                    $admission->bed->save();
                }

                $admission->bed_id = null;
            }

            // Handle Readmission
            // if ($status === AdmissionStatus::ADMITTED->value) {
            //     $admission->admitted_by_id = $staff->id;
            //     $admission->admission_date = now();
            //     $admission->discharge_date = null;

            //     $bedId = $admission->bed_id;

            //     if (!$bedId) {
            //         return response()->json([
            //             'message' => 'Please select a bed to admit the patient.',
            //             'success' => false,
            //             'status' => 'error',
            //         ], 400);
            //     }

            //     $bed = Bed::find($bedId);

            //     if (!$bed || $bed->status !== "AVAILABLE") {
            //         return response()->json([
            //             'message' => 'Selected bed is not available.',
            //             'success' => false,
            //             'status' => 'error',
            //         ], 400);
            //     }

            //     $this->reverseDischargePayment($admission, $staff);

            //     // Assign bed
            //     $admission->bed_id = $bed->id;
            //     $bed->status = "OCCUPIED";
            //     $bed->save();
            // }

            if ($status === AdmissionStatus::DISCHARGED->value) {
                $this->handleDischargePayment($admission, $staff);
            }

            $admission->status = $status;
            $admission->last_updated_by_id = $staff->id;
            $admission->save();

            return response()->json([
                'message' => 'Admission status updated successfully',
                'success' => true,
                'status' => 'success',
                'data' => $admission->fresh(['patient', 'bed', 'treatments.payments']),
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    // private function handleDischargePayment(Admission $admission, User $staff): void
    // {
    //     try {
    //         $user = $admission->patient;

    //         if (!$user || !$user->wallet) {
    //             throw new Exception('Patient or wallet not found.');
    //         }

    //         $wallet = $user->wallet;

    //         $admittedAt = Carbon::parse($admission->admission_date);
    //         $dischargedAt = now();
    //         $daysUsed = $admittedAt->diffInDays($dischargedAt) ?: 1;

    //         // Get bed space service price or use default
    //         $bedService = Service::where('name', 'bed space')->first();
    //         $bedPrice = $bedService?->price ?? 1000;
    //         $bedCost = $bedPrice * $daysUsed;

    //         // Prescription total
    //         $prescriptionsCost = $admission->prescriptions->sum('price');

    //         $totalCost = $bedCost + $prescriptionsCost;
    //         $amountPaid = 0;
    //         $paymentStatus = 'pending';

    //         if ($wallet->balance >= $totalCost) {
    //             $wallet->balance -= $totalCost;
    //             $amountPaid = $totalCost;
    //             $paymentStatus = 'completed';
    //         } else {
    //             $amountPaid = $wallet->balance;
    //             $wallet->outstanding_balance += ($totalCost - $wallet->balance);
    //             $wallet->balance = 0;
    //         }

    //         $wallet->save();

    //         Payment::create([
    //             'amount' => $amountPaid,
    //             'amount_payable' => $totalCost,
    //             'transaction_reference' => strtoupper(Str::random(10)),
    //             'reference' => strtoupper(Str::random(10)),
    //             'type' => 'ADMISSION',
    //             'status' => $paymentStatus,
    //             'payment_method' => 'WALLET',
    //             'remark' => 'Discharge payment for admission ID ' . $admission->id,
    //             'customer_name' => $user->full_name ?? $user->name ?? 'Unknown',
    //             'is_confirmed' => true,
    //             'payable_type' => Admission::class,
    //             'payable_id' => $admission->id,
    //             'added_by_id' => $staff->id,
    //         ]);
    //     } catch (Throwable $e) {
    //         Log::error('Discharge payment failed: ' . $e->getMessage(), [
    //             'admission_id' => $admission->id,
    //             'user_id' => $staff->id,
    //         ]);

    //         throw new Exception('Unable to process discharge payment. Please try again.');
    //     }
    // }

    private function handleDischargePayment(Admission $admission, User $staff): void
    {
        try {
            $user = $admission->patient;

            if (!$user || !$user->wallet) {
                throw new Exception('Patient or wallet not found.');
            }

            $wallet = $user->wallet;

            $admittedAt = Carbon::parse($admission->admission_date);
            $dischargedAt = now();
            $daysUsed = $admittedAt->diffInDays($dischargedAt) ?: 1;

            // Get bed space service price or default
            $bedService = Service::where('name', 'bed space')->first();
            $bedPrice = $bedService?->price ?? 1000;
            $bedCost = $bedPrice * $daysUsed;

            $treatments = $admission->treatments()->with('items.product')->get();

            // Sum up ALL treatments
            $treatmentsCost = $treatments->sum(function ($treatment) {
                $itemsTotal = collect((array) $treatment->items)->sum(function ($item) {
                    return $item->product && $item->product->unit_price
                        ? floatval($item->product->unit_price) * $item->quantity
                        : 0;
                });

                $consultationService = Service::where('type', 'CONSULTATION_FEE')->first();
                $consultationFee = $consultationService ? floatval($consultationService->price) : 0;

                return $itemsTotal + $consultationFee;
            });

            $totalCost = $bedCost + $treatmentsCost;
            $amountPaid = 0;
            $paymentStatus = 'CREATED';

            if ($wallet->deposit_balance >= $totalCost) {
                $wallet->deposit_balance -= $totalCost;
                $amountPaid = $totalCost;
                $paymentStatus = 'COMPLETED';
            } else {
                $amountPaid = $wallet->deposit_balance;
                $wallet->outstanding_balance += ($totalCost - $wallet->deposit_balance);
                $wallet->deposit_balance = 0;
            }

            $wallet->save();

            Payment::create([
                'amount' => $amountPaid,
                'amount_payable' => $totalCost,
                'transaction_reference' => strtoupper(Str::random(10)),
                'reference' => strtoupper(Str::random(10)),
                'type' => 'ADMISSION',
                'status' => $paymentStatus,
                'payment_method' => 'WALLET',
                'remark' => 'Discharge payment for admission ID ' . $admission->id,
                'customer_name' => $user->full_name ?? $user->name ?? 'Unknown',
                'is_confirmed' => true,
                'payable_type' => Admission::class,
                'payable_id' => $admission->id,
                'added_by_id' => $staff->id,
            ]);
        } catch (Throwable $e) {
            Log::error('Discharge payment failed: ' . $e->getMessage(), [
                'admission_id' => $admission->id,
                'user_id' => $staff->id,
            ]);

            throw new Exception('Unable to process discharge payment. Please try again.');
        }
    }

    // private function reverseDischargePayment(Admission $admission, User $staff): void
    // {
    //     $user = $admission->patient->user;
    //     $wallet = $user->wallet;

    //     // Get the last discharge payment for this admission
    //     $lastDischargePayment = Payment::where('payable_type', Admission::class)
    //         ->where('payable_id', $admission->id)
    //         ->where('type', 'debit')
    //         ->where('status', 'completed')
    //         ->latest()
    //         ->first();

    //     if (!$lastDischargePayment) {
    //         return; // No discharge payment found to reverse
    //     }

    //     // Refund the wallet
    //     $wallet->balance += $lastDischargePayment->amount;
    //     $wallet->save();

    //     // Log reversal as a credit
    //     Payment::create([
    //         'amount' => $lastDischargePayment->amount,
    //         'amount_payable' => $lastDischargePayment->amount,
    //         'transaction_reference' => Str::uuid(),
    //         'reference' => 'REVERSAL-' . strtoupper(Str::random(8)),
    //         'type' => 'credit',
    //         'status' => 'completed',
    //         'payment_method' => 'wallet',
    //         'remark' => 'Readmission credit reversal for admission ID ' . $admission->id,
    //         'customer_name' => $user->full_name ?? $user->name ?? 'Unknown',
    //         'is_confirmed' => true,
    //         'payable_type' => Admission::class,
    //         'payable_id' => $admission->id,
    //         'added_by_id' => $staff->id,
    //     ]);

    //     // Optionally flag the original payment as reversed
    //     // $lastDischargePayment->update(['status' => 'reversed']);
    // }

    public function createAdmissionInvestigationNote(Request $request, $admissionId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        try {
            $admission = Admission::find($admissionId);

            if (!$admission) {
                return response()->json([
                    'message' => 'Admission not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            if ($admission->status === 'DISCHARGE') {
                return response()->json([
                    'message' => 'Cannot add investigation. Patient has been discharged.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $note = new Note([
                'title' => $request->title,
                // 'content' => $request->content,
                'created_by_id' => Auth::id(),
                'noteable_id' => $admission->id,
                'noteable_type' => Admission::class,
            ]);

            if (!$note->save()) {
                return response()->json([
                    'message' => 'Note not saved due to unknown error.',
                    'success' => false,
                    'status' => 'error',
                ], 500);
            }

            return response()->json([
                'message' => 'Note added successfully',
                'success' => true,
                'status' => 'success',
            ], 201);
        } catch (Exception $e) {
            Log::error('Create Admission Note Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function updateAdmissionNote(Request $request, $admissionId)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            "note_id" => 'required|numeric'
        ]);

        try {
            $admission = Admission::find($admissionId);

            if (!$admission) {
                return response()->json([
                    'message' => 'Admission not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            if ($admission->status === 'DISCHARGE') {
                return response()->json([
                    'message' => 'Cannot update investigation note. Patient has been discharged.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $noteId = $request["note_id"];

            $note = Note::where('id', $noteId)
                ->where('noteable_id', $admission->id)
                ->where('noteable_type', Admission::class)
                ->first();

            if (!$note) {
                return response()->json([
                    'message' => 'Investigation Note not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            $note->title = $request['title'];
            $note->content = $request['content'];
            $note->last_updated_by = Auth::id();
            $note->save();
            // $note->update([
            //     'title' => $request->title,
            //     'content' => $request->content,
            //     'last_updated_by_id' => Auth::id(),
            // ]);

            return response()->json([
                'message' => 'Note updated successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error('Update Admission Note Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    // private function toggleStatus(string $id, string $status)
    // {
    //     try {
    //         $staff = Auth::user();

    //         $admission = Admission::with([
    //             'patient',
    //             'addedBy',
    //             'bed',
    //             'lastUpdatedBy',
    //             'treatments.payments'
    //         ])->find($id);

    //         if (!$admission) {
    //             return response()->json([
    //                 'message' => 'Admission record not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($status == AdmissionStatus::DISCHARGED->value && $admission->status == AdmissionStatus::DISCHARGED->value) {
    //             return response()->json([
    //                 'message' => 'Admission record already marked as discharged',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($status === AdmissionStatus::ADMITTED->value && $admission->status === AdmissionStatus::ADMITTED->value) {
    //             return response()->json([
    //                 'message' => 'Admission record already marked as admitted',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         // Check unpaid payments if discharging
    //         if ($status === AdmissionStatus::DISCHARGED->value && $admission->treatments->isNotEmpty()) {
    //             $hasUnpaid = $admission->treatments->some(function ($treatment) {
    //                 return $treatment->payments->some(function ($payment) {
    //                     return $payment->status !== PaymentStatus::COMPLETED->value;
    //                 });
    //             });

    //             if ($hasUnpaid) {
    //                 return response()->json([
    //                     'message' => 'Cannot discharge the patient. They have outstanding debts.',
    //                     'success' => false,
    //                     'status' => 'error',
    //                 ], 400);
    //             }
    //         }

    //         if ($status === AdmissionStatus::DISCHARGED->value) {
    //             $admission->discharged_by_id = $staff->id;
    //             $admission->discharge_date = now();
    //         }

    //         if ($status === AdmissionStatus::ADMITTED->value) {
    //             $admission->admitted_by_id = $staff->id;
    //             $admission->admission_date = now();
    //             $admission->discharge_date = null;
    //         }

    //         $admission->status = $status;
    //         $admission->last_updated_by_id = $staff->id;
    //         $admission->save();

    //         return response()->json([
    //             'message' => 'Admission status updated successfully',
    //             'success' => true,
    //             'status' => 'success',
    //             'data' => $admission->fresh(['patient', 'treatments.payments']),
    //         ]);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes.',
    //             'success' => false,
    //             'status' => 'error',
    //         ], 500);
    //     }
    // }
}
