<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\VitalSign;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class VitalSignController extends Controller
{
    public function create(Request $request)
    {
        $request->validate(
            [
                'height' => ['nullable', 'numeric'],
                'weight' => ['nullable', 'numeric'],
                'bmi' => ['nullable', 'numeric'],
                'heart_rate' => ['required', 'numeric', 'not_in:INF'],
                'blood_pressure' => ['required', 'string'],
                'temperature' => ['required', 'numeric', 'not_in:INF'],
                'respiratory_rate' => ['required', 'numeric', 'not_in:INF'],
                'patient_id' => ['required', 'numeric'],
            ],
            [
                'heart_rate.required' => 'Please provide the patient heart rate',
                'heart_rate.numeric' => 'The heart rate should be a valid number',
                'blood_pressure.required' => 'Please provide the patient blood pressure reading',
                'blood_pressure.string' => 'The blood pressure reading should be a valid string',
                'temperature.required' => 'Please provide the patient temperature reading',
                'temperature.numeric' => 'The temperature should be a valid number',
                'respiratory_rate.required' => 'Please provide the patient respiratory rate',
                'respiratory_rate.numeric' => 'Respiratory Rate contain invalid data',
                'patient_id.required' => 'Patient Detail is required',
                'patient_id.numeric' => 'Patient Detail contain invalid data',
            ]
        );

        try {
            $staff = Auth::user();
            $patient = Patient::where('id', $request["patient_id"])->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Patient detail not found',
                ], 400);
            }

            $newVitalSign = new VitalSign();
            $newVitalSign->blood_pressure = $request->blood_pressure;
            $newVitalSign->heart_rate = $request->heart_rate;
            $newVitalSign->height = $request->height;
            $newVitalSign->weight = $request->weight;
            $newVitalSign->bmi = $request->bmi;
            $newVitalSign->respiratory_rate =
                $request->respiratory_rate;
            $newVitalSign->temperature = $request->temperature;
            $newVitalSign->patient_id = $patient->id;
            $newVitalSign->added_by_id = $staff->id;
            $newVitalSign->last_updated_by_id = $staff->id;
            $newVitalSign->save();

            return response()->json([
                'message' => 'Vital-Sign Added successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while creating the vital-sign record',
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $q = $request->get('q', '');

            $queryBuilder = VitalSign::with(['lastUpdatedBy.assignedBranch', 'addedBy.assignedBranch', 'patient'])
                ->orderBy("created_at", "desc")
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

            $vitalSigns = $queryBuilder->paginate(50);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Vital signs records retrieved successfully',
                'data' => $vitalSigns
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving vital sign records',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate(
            [
                'height' => ['nullable', 'numeric'],
                'weight' => ['nullable', 'numeric'],
                'bmi' => ['nullable', 'numeric'],
                'heart_rate' => ['required', 'numeric', 'not_in:INF', 'regex:/^\d+$/'],
                'blood_pressure' => ['required', 'string'],
                'temperature' => ['required', 'numeric', 'not_in:INF', 'regex:/^\d+(\.\d+)?$/'],
                'respiratory_rate' => ['required', 'numeric', 'not_in:INF', 'regex:/^\d+$/'],
                'patient_id' => ['required', 'numeric'],
            ],
            [
                'heart_rate.required' => 'Please provide the patient heart rate',
                'heart_rate.numeric' => 'The heart rate should be a valid number',
                'blood_pressure.required' => 'Please provide the patient blood pressure reading',
                'blood_pressure.string' => 'The blood pressure reading should be a valid string',
                'temperature.required' => 'Please provide the patient temperature reading',
                'temperature.numeric' => 'The temperature should be a valid number',
                'respiratory_rate.required' => 'Please provide the patient respiratory rate',
                'respiratory_rate.numeric' => 'Respiratory Rate contain invalid data',
                'patient_id.required' => 'Patient Detail is required',
                'patient_id.numeric' => 'Patient Detail contain invalid data',
            ]
        );

        try {
            $staff = Auth::user();
            $patient = Patient::where('id', $request["patient_id"])->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Patient detail not found',
                ], 400);
            }

            $vitalSign = VitalSign::find($id);

            if (!$vitalSign) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Vital-Sign detail not found',
                ], 400);
            }

            $vitalSign->blood_pressure = $request->blood_pressure;
            $vitalSign->height = $request->height;
            $vitalSign->weight = $request->weight;
            $vitalSign->bmi = $request->bmi;
            $vitalSign->heart_rate = $request->heart_rate;
            $vitalSign->respiratory_rate = $request->respiratory_rate;
            $vitalSign->temperature = $request->temperature;
            $vitalSign->patient_id = $patient->id;
            $vitalSign->added_by_id = $staff->id;
            $vitalSign->last_updated_by_id = $staff->id;
            $vitalSign->save();

            return response()->json([
                'message' => 'Vital-Sign Added successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            // Return the error message for debugging
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while creating the vital-sign record',
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $vitalSign = VitalSign::find($id);

            if (!$vitalSign) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Vital-Sign detail not found',
                ], 400);
            }

            $vitalSign->delete();

            return response()->json([
                'message' => 'Vital Sign deleted successfully.',
                'status' => 'success',
                'success' => true
            ], 200);
        } catch (Exception $e) {
            Log::info('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function createAdmissionVitalSign($createVitalSignDto, $patientId)
    {
        try {
            $staffId = Auth::user()->id;

            $newVitalSign = new VitalSign();
            $newVitalSign->blood_pressure = $createVitalSignDto['blood_pressure'];
            $newVitalSign->heart_rate = $createVitalSignDto['heart_rate'];
            $newVitalSign->respiratory_rate = $createVitalSignDto['respiratory_rate'];
            $newVitalSign->temperature = $createVitalSignDto['temperature'];
            $newVitalSign->patient_id = $patientId;
            $newVitalSign->added_by_id = $staffId;
            $newVitalSign->last_updated_by_id = $staffId;
            $newVitalSign->save();

            return $newVitalSign;
        } catch (Exception $e) {
            Log::info("hey" . $e->getMessage());

            throw new Exception('Something went wrong. Try again in 5 minutes');
        }
    }

    public function updateAdmissionVitalSign($updateVitalSignDto, $patientId, $vitalId)
    {
        try {
            $staffId = Auth::user()->id;

            $vitalSign = VitalSign::find($vitalId);

            if (!$vitalSign) {
                throw new BadRequestException("Vital Sign not found", 400);
            }

            $vitalSign->blood_pressure = $updateVitalSignDto['blood_pressure'];
            $vitalSign->heart_rate = $updateVitalSignDto['heart_rate'];
            $vitalSign->respiratory_rate = $updateVitalSignDto['respiratory_rate'];
            $vitalSign->temperature = $updateVitalSignDto['temperature'];
            $vitalSign->patient_id = $patientId;
            $vitalSign->last_updated_by_id = $staffId;
            $vitalSign->save();

            return $vitalSign;
        } catch (Exception $e) {
            Log::info('hey' . $e->getMessage());

            throw new Exception('Something went wrong. Try again in 5 minutes');
        }
    }
}
