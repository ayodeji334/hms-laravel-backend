<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ServiceTypes;
use App\Models\AnteNatal;
use App\Models\AnteNatalRoutineAssessment;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\PreviousPregnanciesSummary;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class AnteNatalController extends Controller
{
    protected PaymentController $paymentService;
    protected NoteController $noteService;

    public function __construct(PaymentController $paymentService, NoteController $noteService)
    {
        $this->paymentService = $paymentService;
        $this->noteService = $noteService;
    }

    public function createAccount(Request $request)
    {
        $createAnteNatalDto = $request->validate([
            'age_at_marriage' => ['required', 'numeric', 'not_in:NaN', 'not_in:INF'],
            'payment_reference' => ['required', 'string'],
            'date_of_booking' => ['required', 'date_format:Y-m-d'],
            'duration_of_pregnancy_at_registration' => ['required', 'string'],
            'patient_id' => ['required', 'numeric'],
        ], [
            'age_at_marriage.required' => 'Patient age at marriage is required',
            'age_at_marriage.numeric' => 'Patient age at marriage contains invalid data',
            'age_at_marriage.not_in' => 'Patient age at marriage contains invalid data',
            'payment_reference.required' => 'Registration payment reference is required',
            'payment_reference.string' => 'Payment reference must be a string',
            'date_of_booking.required' => 'Expected date of booking is required',
            'date_of_booking.date_format' => 'Expected date of booking contains invalid data',
            'duration_of_pregnancy_at_registration.required' => 'Duration of pregnancy at registration is required',
            'duration_of_pregnancy_at_registration.string' => 'Duration of pregnancy at registration must be a string',
            'patient_id.required' => 'Patient detail is required',
            'patient_id.numeric' => 'Patient detail is invalid',
        ]);

        try {
            $staff = Auth::user();

            // Check if there is already an active antenatal record for this patient
            $existingAnteNatal = AnteNatal::where('status', 'NOT-DELIVERED')
                ->where('patient_id', $createAnteNatalDto['patient_id'])
                ->first();

            if ($existingAnteNatal) {
                return response()->json([
                    'message' => 'You cannot create another record for the patient. There is an existing ongoing ante-natal record',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Check if patient exists
            $patient = Patient::find($createAnteNatalDto['patient_id']);
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            DB::beginTransaction();

            // Load payment model (not just array from service)
            $payment = Payment::where('reference', $createAnteNatalDto['payment_reference'])->first();

            if (!$payment) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Payment reference not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Ensure payment belongs to staff
            if ($payment->patient_id !== $request['patient_id']) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This payment does not belong to the selected patient',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Check if payment already used
            if ($payment->is_used) {
                DB::rollBack();
                return response()->json([
                    'message' => 'This payment has already been used',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Confirm it is for ante-natal and completed
            if (
                empty($payment->payable_type) ||
                $payment->payable_type !== AnteNatal::class
            ) {
                DB::rollBack();
                return response()->json([
                    'message' => 'The payment is not for the ante-natal registration',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($payment->status !== PaymentStatus::COMPLETED->value) {
                DB::rollBack();
                return response()->json([
                    'message' => 'The payment is not yet confirmed',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Generate care ID
            $lastRecord = AnteNatal::orderByDesc('created_at')->select('care_id')->first();
            $lastCareId = $lastRecord?->care_id ?? '000000';

            // Create AnteNatal record
            $newRecord = new AnteNatal();
            $newRecord->booking_date = Carbon::parse($createAnteNatalDto['date_of_booking']);
            $newRecord->age_at_marriage = $createAnteNatalDto['age_at_marriage'];
            $newRecord->duration_of_pregnancy_at_registration = $createAnteNatalDto['duration_of_pregnancy_at_registration'];
            $newRecord->patient_id = $patient->id;
            $newRecord->added_by_id = $staff->id;
            $newRecord->last_updated_by_id = $staff->id;
            $newRecord->care_id = $this->generateNextId($lastCareId);
            $newRecord->save();

            // Attach payment to antenatal
            $payment->payable_id = $newRecord->id;
            $payment->is_used = true;
            $payment->save();

            DB::commit();

            return response()->json([
                'message' => 'Ante-natal account created successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Ante-natal account creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function update(string $id, Request $request)
    {
        try {
            $staff = Auth::user();

            $anteNatalRecord = AnteNatal::with([
                'previousPregnancies',
                'patient'
            ])->find($id);

            if (!$anteNatalRecord) {
                return response()->json([
                    'message' => 'Ante-Natal Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $previousPregnanciesData = $request->input('previous_pregnancies', []);

            DB::transaction(function () use ($anteNatalRecord, $previousPregnanciesData, $staff, $request) {
                // Soft delete old pregnancy summaries
                foreach ($anteNatalRecord->previousPregnancies as $record) {
                    $record->delete();
                }

                // $newPregnancySummaries = [];

                foreach ($previousPregnanciesData as $item) {
                    $summary = new PreviousPregnanciesSummary();
                    $summary->cause_of_death = $item['is_child_still_alive'] ? null : $item['cause_of_death'];
                    $summary->child_age_before_death = $item['is_child_still_alive'] ? null : $item['child_age_before_death'];
                    $summary->child_gender = $item['child_gender'];
                    $summary->child_weight = (string) $item['child_weight'];
                    $summary->complication_during_labour = $item['complication_during_labour'];
                    $summary->complication_during_pregnancy = $item['complication_during_pregnancy'];
                    $summary->date_of_birth = $item['date_of_birth'];
                    $summary->duration_of_pregnancy = $item['duration_of_pregnancy'];
                    $summary->pueperium = $item['pueperium'];
                    $summary->is_child_still_alive = $item['is_child_still_alive'];
                    $summary->added_by_id = $staff->id;
                    $summary->last_updated_by_id = $staff->id;
                    $summary->ante_natal_id = $anteNatalRecord->id;
                    $summary->save();
                }

                // Update AnteNatal record
                $anteNatalRecord->fill([
                    'expected_date_delivery' => $request->expected_date_delivery,
                    'last_menstrual_period' => $request->last_menstrual_period,
                    'total_number_of_children_alive' => $request->total_number_of_children_alive,
                    'total_number_of_children' => $request->total_number_of_children,
                    'has_chest_disease' => $request->has_chest_disease,
                    'has_heart_disease' => $request->has_heart_disease,
                    'has_kidney_disease' => $request->has_kidney_disease,
                    'has_leprosy_disease' => $request->has_leprosy_disease,
                    'has_undergo_operations' => $request->has_undergo_operations,
                    'last_updated_by_id' => $staff->id,
                ]);

                $primary = $request->input('primary_assessment', []);
                $anteNatalRecord->fill([
                    'ankles_swelling' => $primary['ankles_swelling'] ?? null,
                    'vdrl' => $primary['vdrl'] ?? null,
                    'rh' => $primary['rh'] ?? null,
                    'pcv' => $primary['pcv'] ?? null,
                    'abdomen' => $primary['abdomen'] ?? null,
                    'urine_albumin' => $primary['urine_albumin'] ?? null,
                    'urine_sugar' => $primary['urine_sugar'] ?? null,
                    'bleeding' => $primary['bleeding'] ?? null,
                    'breast_and_nipples' => $primary['breast_and_nipples'] ?? null,
                    'cardiovascular_system' => $primary['cardiovascular_system'] ?? null,
                    'discharge' => $primary['discharge'] ?? null,
                    'oedema' => $primary['oedema'] ?? null,
                    'blood_group' => $primary['blood_group'] ?? null,
                    'blood_pressure' => $primary['blood_pressure'] ?? null,
                    'general_condition' => $primary['general_condition'] ?? null,
                    'genotype' => $primary['genotype'] ?? null,
                    'anaemia' => $primary['anaemia'] ?? null,
                    'height' => $primary['height'] ?? null,
                    'pregnancy_history' => $primary['pregnancy_history'] ?? null,
                    'liver' => $primary['liver'] ?? null,
                    'other_abnormalities' => $primary['other_abnormalities'] ?? null,
                    'other_symptoms' => $primary['other_symptoms'] ?? null,
                    'preliminary_pelvic_assessment' => $primary['preliminary_pelvic_assessment'] ?? null,
                    'respiratory_system' => $primary['respiratory_system'] ?? null,
                    'spleen' => $primary['spleen'] ?? null,
                    'urinary_symptoms' => $primary['urinary_symptoms'] ?? null,
                    'vomitting' => $primary['vomitting'] ?? null,
                    'weight' => $primary['weight'] ?? null,
                ]);

                $anteNatalRecord->save();
            });

            return response()->json([
                'message' => 'Ante-natal record updated successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::info($e);

            return response()->json([
                'message' => $e->getMessage() ?? 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $search = $request->input('q');
            $status = strtoupper($request->input('status', ''));
            $query = AnteNatal::with([
                'patient',
                'previousPregnancies.lastUpdatedBy'
            ])->orderByDesc('updated_at');

            if ($status && $status !== 'ALL') {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->whereHas('patient', function ($q2) use ($search) {
                        $q2->where('firstname', $search)
                            ->orWhere('lastname', $search)
                            ->orWhere('patient_id', $search);
                    })->orWhere('care_id', $search);
                });
            }

            // $query->;

            $data = $query->paginate($limit);

            return response()->json([
                'message' => 'Ante-Natal fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data
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

    public function delete($id)
    {
        try {
            $antenatal = AnteNatal::find($id);

            if (!$antenatal) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => "Ante-natal detail not found."
                ], 400);
            }

            $antenatal->delete();

            return response()->json([
                'message' => 'Ante-Natal Account deleted successfully.',
                'status' => 'success',
                'success' => true
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            // Handle any other exception
            return response()->json([
                'message' => 'An error occurred while trying to delete the patient account',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $anteNatalRecord = AnteNatal::with([
                "payment",
                'previousPregnancies.lastUpdatedBy',
                'scanReports.createdBy',
                'scanReports.lastUpdatedBy',
                'patient',
                "patient.organisationHmo",
                'routineCheckup.examiner.assignedBranch',
                'routineCheckup.addedBy.assignedBranch',
                'prescriptions.items.product',
                'prescriptions.requestedBy.assignedBranch',
            ])
                ->with(['routineCheckup' => function ($query) {
                    $query->orderByDesc('updated_at');
                }])
                ->find($id);

            if (!$anteNatalRecord) {
                return response()->json([
                    'message' => 'Ante-Natal Record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            return response()->json([
                'message' => 'Ante-natal record fetched successfully',
                'success' => true,
                'status' => 'success',
                'data' => $anteNatalRecord,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching ante-natal record', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function addRoutineAssessment(Request $request, $id)
    {
        $request->validate([
            'gestational_age' => ['nullable', 'string'],
            'height_of_fundus' => ['nullable', 'string'],
            'presentation_and_position' => ['nullable', 'string'],
            'presenting_part_to_brim' => ['nullable', 'string'],
            'foetal_heart' => ['nullable', 'string'],
            'risk' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'urine' => ['nullable', 'string'],
            'blood_pressure' => ['nullable', 'string'],
            'pcv' => ['nullable', 'string'],
            'oedemia' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'weight' => ['nullable', 'string'],
            'examiner' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ], [
            'gestational_age.string' => 'Gestational Age field contain invalid data',
            'height_of_fundus.string' => 'Height of Fundus field contain invalid data',
            'presentation_and_position.string' => 'Presentation and position field contain invalid data',
            'presenting_part_to_brim.string' => 'Presenting Part to brim field contain invalid data',
            'foetal_heart.string' => 'Foetal Heart field contain invalid data',
            'risk.string' => 'Risk field contain invalid data',
            'comment.string' => 'Comment field contain invalid data',
            'urine.string' => 'Urine field contain invalid data',
            'blood_pressure.string' => 'Blood Pressure field contain invalid data',
            'pcv.string' => 'PCV field contain invalid data',
            'oedemia.string' => 'Oedemia field contain invalid data',
            'remarks.string' => 'Remarks field contain invalid data',
            'weight.string' => 'Weight field contain invalid data',
            'examiner.integer' => 'Examiner field contain invalid data',
            'date.date' => 'Invalid date',
        ]);

        try {
            $anteNatal = AnteNatal::with('routineCheckup')->find($id);

            if (!$anteNatal) {
                return response()->json([
                    'message' => 'Ante Natal record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($anteNatal->status === 'DELIVERED') {
                return response()->json([
                    'message' => 'You cannot add routine checkup report because the record has been marked as completed',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $examiner = User::find($request->input('examiner'));

            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Create new assessment
            $assessment = new AnteNatalRoutineAssessment();
            $assessment->gestational_age = $request->input('gestational_age');
            $assessment->risk = $request->input('risk');
            $assessment->comment = $request->input('comment');
            $assessment->date = $request->input('date');
            $assessment->height_of_fundus = $request->input('height_of_fundus');
            $assessment->presentation_and_position = $request->input('presentation_and_position');
            $assessment->presenting_part_to_brim = $request->input('presenting_part_to_brim');
            $assessment->foetal_heart = $request->input('foetal_heart');
            $assessment->urine = $request->input('urine');
            $assessment->blood_pressure = $request->input('blood_pressure');
            $assessment->weight = $request->input('weight');
            $assessment->pcv = $request->input('pcv');
            $assessment->oedemia = $request->input('oedemia');
            $assessment->remarks = $request->input('remarks');
            $assessment->examiner_id = $examiner->id;
            $assessment->added_by_id = Auth::user()->id;
            $assessment->ante_natal_id = $anteNatal->id;
            $assessment->save();

            return response()->json([
                'message' => 'Ante Natal Routine Assessment added successfully',
                'success' => true,
                'status' => 'success',
            ], 201);
        } catch (Exception $e) {
            Log::error('Error adding routine assessment', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function editRoutineAssessment(Request $request, $id)
    {
        try {
            $examiner = User::find($request->input('examiner'));

            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $assessment = AnteNatalRoutineAssessment::find($id);

            if (!$assessment) {
                return response()->json([
                    'message' => 'Routine Assessment not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $assessment->gestational_age = $request->input('gestational_age');
            $assessment->risk = $request->input('risk');
            $assessment->comment = $request->input('comment');
            $assessment->date = $request->input('date');
            $assessment->height_of_fundus = $request->input('height_of_fundus');
            $assessment->presentation_and_position = $request->input('presentation_and_position');
            $assessment->presenting_part_to_brim = $request->input('presenting_part_to_brim');
            $assessment->foetal_heart = $request->input('foetal_heart');
            $assessment->urine = $request->input('urine');
            $assessment->blood_pressure = $request->input('blood_pressure');
            $assessment->weight = $request->input('weight');
            $assessment->pcv = $request->input('pcv');
            $assessment->oedemia = $request->input('oedemia');
            $assessment->remarks = $request->input('remarks');
            $assessment->examiner_id = $examiner->id;
            $assessment->last_updated_by_id = Auth::user()->id;
            $assessment->save();

            return response()->json([
                'message' => 'Ante Natal Routine Assessment updated successfully',
                'success' => true,
                'status' => 'success',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error updating routine assessment', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function addScanReport(Request $request,  $id)
    {
        $validatedData = $request->validate([
            'content' => ['required', 'string'],
            'title' => ['required', 'string'],
        ]);

        try {
            $anteNatalRecord = AnteNatal::with('scanReports')->find($id);

            if (!$anteNatalRecord) {
                return response()->json([
                    'message' => 'Ante Natal record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($anteNatalRecord->status === "DELIVERED") {
                return response()->json([
                    'message' => 'You cannot add scan report, because the ante-natal has been marked as completed',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $note = $this->noteService->create($validatedData);
            $anteNatalRecord->scanReports()->save($note);

            return response()->json([
                'message' => 'Antenatal Scan Report added successfully',
                'success' => true,
                'status' => 'success',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error adding scan report', ['error' => $e->getMessage()]);

            // Return error message if something goes wrong
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        // Validate request
        $validated = $request->validate(['status' => [
            'required',
            'string',
            Rule::in(['delivered', 'not-delivered']),
        ]], [
            'status.required' => 'Status field is required',
            'status.string' => 'Status field must be a valid string',
            'status.in' => 'Status field contains invalid data (allowed: delivered, not-delivered)',
        ]);

        try {
            $anteNatal = AnteNatal::find($id);

            if (!$anteNatal) {
                return response()->json([
                    'message' => 'Ante Natal record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $anteNatal->status = $validated['status'] === 'delivered' ? 'DELIVERED' : 'NOT_DELIVERED';
            $anteNatal->last_updated_by_id = Auth::id();


            Log::info($anteNatal);
            $anteNatal->save();

            return response()->json([
                'message' => 'Ante-Natal status updated successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error updating Ante-Natal status', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateRegistration(Request $request, $id)
    {
        $validated = $request->validate([
            'patient_id' => 'required|uuid|exists:patients,id',
            'payment_reference' => 'required|string',
            'date_of_booking' => 'required|date',
            'age_at_marriage' => 'nullable|string',
            'duration_of_pregnancy_at_registration' => 'nullable|string',
        ]);

        try {
            $anteNatal = AnteNatal::with('patient')->where('id', $id)
                ->orWhereHas('patient', fn($q) => $q->where('id', $id))
                ->first();

            if (!$anteNatal) {
                return response()->json([
                    'message' => 'Ante natal record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($anteNatal->status === 'delivered') {
                return response()->json([
                    'message' => 'You cannot update the record because it has been marked as delivered',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $patient = Patient::find($validated['patient_id']);
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $payment = Payment::where('reference', $validated['payment_reference'])->with('service')->first();
            if (!$payment) {
                return response()->json([
                    'message' => 'Payment reference detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($payment->service->type !== 'ante-natal') {
                return response()->json([
                    'message' => 'The payment is not for the ante-natal registration',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($payment->status !== 'paid') {
                return response()->json([
                    'message' => 'The payment is not yet confirmed',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $anteNatal->booking_date = $validated['date_of_booking'];
            $anteNatal->age_at_marriage = $validated['age_at_marriage'] ?? null;
            $anteNatal->duration_of_pregnancy_at_registration = $validated['duration_of_pregnancy_at_registration'] ?? null;
            $anteNatal->registration_payment_id = $payment->id;
            $anteNatal->patient_id = $patient->id;
            $anteNatal->last_updated_by_id = Auth::id();
            $anteNatal->save();

            return response()->json([
                'message' => 'Ante-natal record updated successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function remove($id)
    {
        try {
            $note = Note::find($id);

            if (!$note) {
                return response()->json([
                    'message' => 'The note detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $note->delete();

            return response()->json([
                'message' => 'Note Deleted Successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error deleting note', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    private function generateNextId($lastId): string
    {
        return str_pad((int) $lastId + 1, 6, '0', STR_PAD_LEFT);
    }
}
