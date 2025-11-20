<?php

namespace App\Http\Controllers;

use App\Enums\VisitationStatus;
use App\Models\PhysicalExamination;
use App\Models\Visitation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PhysicalExaminationController extends Controller
{
    /**
     * Create a new Physical Examination
     */
    public function create(Request $request)
    {
        $this->validateRequest($request);

        try {
            $staff = Auth::user();
            $visitation = Visitation::find($request->visitation_id);

            if (!$visitation) {
                return response()->json([
                    'message' => "Visitation detail not found",
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($visitation->status === VisitationStatus::PENDING->value) {
                return response()->json([
                    'message' => 'You need to accept the visitation before adding the physical examination detail',
                    'status' => 'error',
                    'success' => false
                ], 400);
            }

            if ($visitation->status === VisitationStatus::CONSULTED->value) {
                return response()->json([
                    'message' => 'You cannot add the physical examination detail because the appointment has been marked as consulted (i.e., completed)',
                    'status' => 'error'
                ], 400);
            }

            $physicalExamination = new PhysicalExamination();
            $this->mapRequestToModel($physicalExamination, $request, $staff);

            $physicalExamination->save();

            return response()->json([
                'message' => 'Physical Examination added successfully',
                'status' => 'success',
                'success' => true,
                'data' => $physicalExamination
            ]);
        } catch (Exception $e) {
            Log::info('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }


    /**
     * Fetch all physical examinations (paginated)
     */
    public function findAll(Request $request)
    {
        try {
            $data = PhysicalExamination::with('visitation.patient', 'addedBy', 'lastUpdatedBy')
                ->latest()
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'message' => 'Physical examinations retrieved successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $e) {
            Log::error("FindAll Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Unable to fetch physical examinations',
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Fetch a single physical examination
     */
    public function findOne($id)
    {
        try {
            $record = PhysicalExamination::with('visitation.patient', 'addedBy', 'lastUpdatedBy')
                ->find($id);

            if (!$record) {
                return response()->json([
                    'message' => "Physical Examination not found",
                    'status' => 'error',
                    'success' => false
                ], 404);
            }

            return response()->json([
                'message' => "Record retrieved successfully",
                'status' => 'success',
                'success' => true,
                'data' => $record
            ]);
        } catch (Exception $e) {
            Log::error("FindOne Error: " . $e->getMessage());
            return response()->json([
                'message' => "Unable to fetch record",
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Update physical examination
     */
    public function update(Request $request, $id)
    {
        $this->validateRequest($request, $isUpdate = true);

        try {
            $record = PhysicalExamination::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'Record not found',
                    'status' => 'error'
                ], 404);
            }

            $staff = Auth::user();

            $this->mapRequestToModel($record, $request, $staff, $isUpdate = true);
            $record->save();

            return response()->json([
                'message' => 'Physical Examination updated successfully',
                'status' => 'success',
                'data' => $record
            ]);
        } catch (Exception $e) {
            Log::error("Update Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again later.',
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Delete physical examination (if needed)
     */
    public function delete($id)
    {
        try {
            $record = PhysicalExamination::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'Record not found',
                    'status' => 'error'
                ], 404);
            }

            $record->delete();

            return response()->json([
                'message' => 'Record deleted successfully',
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            Log::error("Delete Error: " . $e->getMessage());
            return response()->json([
                'message' => 'Unable to delete record',
                'status' => 'error'
            ], 500);
        }
    }


    /**
     * Shared Validation Logic
     */
    private function validateRequest(Request $request, $isUpdate = false)
    {
        $rules = [
            'visitation_id' => $isUpdate ? 'nullable|numeric' : 'required|numeric',
            'diseases' => 'nullable|array',
            'right_eye_vision_acuity_without_glasses' => 'nullable|string',
            'left_eye_vision_acuity_without_glasses' => 'nullable|string',
            'right_eye_vision_acuity_with_glasses' => 'nullable|string',
            'left_eye_vision_acuity_with_glasses' => 'nullable|string',
            'apex_beat' => 'nullable|string',
            'bmi' => 'nullable|numeric',
            'heart_sound' => 'nullable|string',
            'blood_pressure' => 'nullable|string',
            'pulse' => 'nullable|string',
            'respiratory' => 'nullable|array',
            'abdominal' => 'nullable|array',
            'rectal' => 'nullable|array',
            'breast' => 'nullable|array',
            'genital' => 'nullable|array',
            'mental_altertness' => 'nullable|string',
            'glasgow_coma_scale' => 'nullable|string',
            'recommendation_status' => 'nullable|string',
            'other_examination' => 'nullable|string',
            'recommended_test.*' => 'numeric',
        ];

        $request->validate($rules);
    }


    /**
     * Map request fields to model
     */
    private function mapRequestToModel(PhysicalExamination $model, Request $request, $staff, $isUpdate = false)
    {
        if (!$isUpdate) {
            $model->visitation_id = $request->visitation_id;
            $model->added_by_id = $staff->id;
        }

        $model->last_updated_by_id = $staff->id;

        $fields = [
            'apex_beat',
            'blood_pressure',
            'bmi',
            'heart_sound',
            'pulse',
            'mental_altertness',
            'glasgow_coma_scale',
            'recommendation_status',
            'other_examination',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $model->$field = $request->$field;
            }
        }

        if ($request->has('diseases')) {
            foreach ($request->diseases as $key => $disease) {
                $model->{'is_suffer_' . strtolower($key) . '_before'} =
                    isset($disease['value']) ? strtoupper($disease['value']) === "YES" : null;

                $model->{'is_suffer_' . strtolower($key) . '_before_remark'} =
                    $disease['comment'] ?? null;
            }
        }
    }
}
