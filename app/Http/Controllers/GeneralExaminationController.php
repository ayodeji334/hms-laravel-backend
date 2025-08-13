<?php

namespace App\Http\Controllers;

use App\Models\GeneralExamination;
use App\Models\Patient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GeneralExaminationController extends Controller
{
    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $searchQuery = $request->input('q');

            $query = GeneralExamination::with([
                'addedBy',
                'patient',
                'lastUpdatedBy'
            ])->latest('updated_at');

            // Search filter
            if ($searchQuery) {
                $query->whereHas('patient', function ($q) use ($searchQuery) {
                    $q->where('firstname', $searchQuery)
                        ->orWhere('lastname', $searchQuery)
                        ->orWhere('phone_number', $searchQuery)
                        ->orWhere('patient_reg_no', $searchQuery);
                });
            }

            $records = $query->paginate($limit);

            return response()->json([
                'message' => 'Records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $records
            ]);
        } catch (Exception $e) {
            Log::error($e);

            return response()->json([
                'message' => "Something went wrong",
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update(string $id, Request $request)
    {
        try {
            $staff = Auth::user();

            $examination = GeneralExamination::find($id);
            if (!$examination) {
                return response()->json([
                    'message' => 'Examination not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($request->patient_id) {
                $patient = Patient::find($request->patient_id);
                if (!$patient) {
                    return response()->json([
                        'message' => 'Patient detail not found',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
                $examination->patient_id = $patient->id;
            }

            $examination->fill([
                'is_admitted_before' => $request->is_admitted_before,
                'is_presently_on_medication_or_treatment' => $request->is_presently_on_medication_or_treatment,
                'is_suffer_abnormal_bleeding_before' => $request->is_suffer_abnormal_bleeding_before,
                'is_suffer_allergy_before' => $request->is_suffer_allergy_before,
                'is_suffer_asthma_or_breathlessness_before' => $request->is_suffer_asthma_or_breathlessness_before,
                'is_suffer_congenital_deformity_before' => $request->is_suffer_congenital_deformity_before,
                'is_suffer_deafness_or_ear_discharge_before' => $request->is_suffer_deafness_or_ear_discharge_before,
                'is_suffer_diabetes_mellitus_before' => $request->is_suffer_diabetes_mellitus_before,
                'is_suffer_epilepsy_or_fits_before' => $request->is_suffer_epilepsy_or_fits_before,
                'is_suffer_fainting_attacks_or_griddiness_before' => $request->is_suffer_fainting_attacks_or_griddiness_before,
                'is_suffer_foot_knee_back_neck_trouble_before' => $request->is_suffer_foot_knee_back_neck_trouble_before,
                'is_suffer_jaundice_before' => $request->is_suffer_jaundice_before,
                'is_suffer_recurrent_headaches_or_migraine_before' => $request->is_suffer_recurrent_headaches_or_migraine_before,
                'is_suffer_recurrent_indigestion_before' => $request->is_suffer_recurrent_indigestion_before,
                'is_suffer_sickle_cells_disease_before' => $request->is_suffer_sickle_cells_disease_before,
                'is_suffer_skin_disorder_before' => $request->is_suffer_skin_disorder_before,
                'is_suffer_sleep_disturbance_before' => $request->is_suffer_sleep_disturbance_before,
                'is_suffer_tuberculosis_before' => $request->is_suffer_tuberculosis_before,
                'is_undergo_surgical_operation_before' => $request->is_undergo_surgical_operation_before,
                'allergies' => $request->allergies,
                'family_sickness_history' => $request->family_sickness_history,
                'immunized_against_diseases' => $request->immunized_against_diseases,
                'last_updated_by' => $staff->id,
            ]);
            $examination->save();

            return response()->json([
                'message' => 'General Examination updated successfully',
                'status' => 'success',
                'success' => true,
                'data' => $examination,
            ]);
        } catch (Exception $e) {
            Log::error($e);

            $statusCode = $e->getCode() === 400 ? 400 : 500;
            $message = $statusCode === 400 ? $e->getMessage() : 'Something went wrong. Try again in 5 minutes';

            return response()->json([
                'message' => $message,
                'status' => 'error',
                'success' => false,
            ], $statusCode);
        }
    }

    public function findOne(string $id)
    {
        try {
            $generalExaminationRecord = GeneralExamination::with([
                'addedBy',
                'lastUpdatedBy',
                'patient'
            ])->find($id);

            if (!$generalExaminationRecord) {
                return response()->json([
                    'message' => 'General Examination record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'General Examination retrieved successfully',
                'status' => 'success',
                'success' => true,
                'data' => $generalExaminationRecord,
            ]);
        } catch (Exception $e) {
            Log::error($e);

            $statusCode = $e->getCode() === 400 ? 400 : 500;
            $message = $statusCode === 400 ? $e->getMessage() : 'Something went wrong. Try again in 5 minutes';

            return response()->json([
                'message' => $message,
                'status' => 'error',
                'success' => false,
            ], $statusCode);
        }
    }

    public function delete(string $id)
    {
        try {
            $generalExaminationRecord = GeneralExamination::with([
                'addedBy',
                'lastUpdatedBy',
                'patient'
            ])->find($id);

            if (!$generalExaminationRecord) {
                return response()->json([
                    'message' => 'General Examination record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $generalExaminationRecord->delete();

            return response()->json([
                'message' => 'General Examination deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error($e);

            $statusCode = $e->getCode() === 400 ? 400 : 500;
            $message = $statusCode === 400 ? $e->getMessage() : 'Something went wrong. Try again in 5 minutes';

            return response()->json([
                'message' => $message,
                'status' => 'error',
                'success' => false,
            ], $statusCode);
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'is_suffer_skin_disorder_before' => ['required', 'boolean'],
            'is_admitted_before' => ['required', 'boolean'],
            'is_undergo_surgical_operation_before' => ['required', 'boolean'],
            'is_presently_on_medication_or_treatment' => ['required', 'boolean'],
            'is_suffer_asthma_or_breathlessness_before' => ['required', 'boolean'],
            'is_suffer_deafness_or_ear_discharge_before' => ['required', 'boolean'],
            'is_suffer_sleep_disturbance_before' => ['required', 'boolean'],
            'is_suffer_abnormal_bleeding_before' => ['required', 'boolean'],
            'is_suffer_fainting_attacks_or_griddiness_before' => ['required', 'boolean'],
            'is_suffer_epilepsy_or_fits_before' => ['required', 'boolean'],
            'is_suffer_recurrent_headaches_or_migraine_before' => ['required', 'boolean'],
            'is_suffer_recurrent_indigestion_before' => ['required', 'boolean'],
            'is_suffer_diabetes_mellitus_before' => ['required', 'boolean'],
            'is_suffer_jaundice_before' => ['required', 'boolean'],
            'is_suffer_sickle_cells_disease_before' => ['required', 'boolean'],
            'is_suffer_tuberculosis_before' => ['required', 'boolean'],
            'is_suffer_congenital_deformity_before' => ['required', 'boolean'],
            'is_suffer_foot_knee_back_neck_trouble_before' => ['required', 'boolean'],
            'is_suffer_allergy_before' => ['required', 'boolean'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'allergies' => [
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->input('is_suffer_allergy_before') && empty($value)) {
                        $fail('Allergies is required when allergy condition is true');
                    }
                },
                'array',
            ],
            'allergies.*.type' => ['required_with:allergies', 'string'],
            'allergies.*.value' => ['required_with:allergies', 'string'],
            'family_sickness_history' => ['sometimes', 'array'],
            'family_sickness_history.*' => ['string'],
            'immunized_against_diseases' => ['sometimes', 'array'],
            'immunized_against_diseases.*' => ['string'],
        ], [
            '*.required' => ':attribute is required',
            '*.boolean' => ':attribute contains invalid data',
            'patient_id.integer' => 'Patient Detail contains invalid data',
            'patient_id.exists' => 'Patient not found',
            'allergies.required' => 'Allergies is required when allergy condition is true',
            'allergies.array' => 'Invalid Allergies Detail',
            'allergies.*.type.required_with' => 'Allergy type is required',
            'allergies.*.value.required_with' => 'Allergy value is required',
            'family_sickness_history.array' => 'Invalid Family Sickness History Detail',
            'family_sickness_history.*.string' => 'Family Sickness History contains invalid data',
            'immunized_against_diseases.array' => 'Invalid Diseases immunized against Detail',
            'immunized_against_diseases.*.string' => 'Diseases immunized against contains invalid data',
        ]);

        try {
            $staff = Auth::user();

            $patient = Patient::find($request->patient_id);
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $examination = GeneralExamination::create([
                'added_by_id' => $staff->id,
                'patient_id' => $patient->id,
                'is_admitted_before' => $request->is_admitted_before,
                'is_presently_on_medication_or_treatment' => $request->is_presently_on_medication_or_treatment,
                'is_suffer_abnormal_bleeding_before' => $request->is_suffer_abnormal_bleeding_before,
                'is_suffer_allergy_before' => $request->is_suffer_allergy_before,
                'is_suffer_asthma_or_breathlessness_before' => $request->is_suffer_asthma_or_breathlessness_before,
                'is_suffer_congenital_deformity_before' => $request->is_suffer_congenital_deformity_before,
                'is_suffer_deafness_or_ear_discharge_before' => $request->is_suffer_deafness_or_ear_discharge_before,
                'is_suffer_diabetes_mellitus_before' => $request->is_suffer_diabetes_mellitus_before,
                'is_suffer_epilepsy_or_fits_before' => $request->is_suffer_epilepsy_or_fits_before,
                'is_suffer_fainting_attacks_or_griddiness_before' => $request->is_suffer_fainting_attacks_or_griddiness_before,
                'is_suffer_foot_knee_back_neck_trouble_before' => $request->is_suffer_foot_knee_back_neck_trouble_before,
                'is_suffer_jaundice_before' => $request->is_suffer_jaundice_before,
                'is_suffer_recurrent_headaches_or_migraine_before' => $request->is_suffer_recurrent_headaches_or_migraine_before,
                'is_suffer_recurrent_indigestion_before' => $request->is_suffer_recurrent_indigestion_before,
                'is_suffer_sickle_cells_disease_before' => $request->is_suffer_sickle_cells_disease_before,
                'is_suffer_skin_disorder_before' => $request->is_suffer_skin_disorder_before,
                'is_suffer_sleep_disturbance_before' => $request->is_suffer_sleep_disturbance_before,
                'is_suffer_tuberculosis_before' => $request->is_suffer_tuberculosis_before,
                'is_undergo_surgical_operation_before' => $request->is_undergo_surgical_operation_before,
                'allergies' => $request->allergies,
                'family_sickness_history' => $request->family_sickness_history,
                'immunized_against_diseases' => $request->immunized_against_diseases,
                'last_updated_by_id' => $staff->id,
            ]);

            return response()->json([
                'message' => 'General Examination created successfully',
                'status' => 'success',
                'success' => true,
                'data' => $examination,
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            $message = 'Something went wrong. Try again in 5 minutes';

            return response()->json([
                'message' => $message,
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
