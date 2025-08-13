<?php

namespace App\Http\Controllers;

use App\Enums\VisitationStatus;
use App\Models\PhysicalExamination;
use App\Models\Visitation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PhysicalExaminationController extends Controller
{
    //
    public function create(Request $request)
    {
        $request->validate([
            'visitation_id' => 'required|numeric',
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
            'recommended_test' => 'nullable|array',
            'recommended_test.*' => 'numeric',
        ], [
            'visitation_id.required' => 'Visitation Field is required',
            'visitation_id.numeric' => 'Visitation Field contains invalid data',
            'right_eye_vision_acuity_without_glasses.string' => 'Right Eye Without Glasses Field contains invalid data',
            'left_eye_vision_acuity_without_glasses.string' => 'Left Eye Without Glasses Field contains invalid data',
            'right_eye_vision_acuity_with_glasses.string' => 'Right Eye With Glasses Field contains invalid data',
            'left_eye_vision_acuity_with_glasses.string' => 'Left Eye With Glasses Field contains invalid data',
            'apex_beat.string' => 'Apex Beats Field contains invalid data',
            'bmi.numeric' => 'BMI Field contains invalid data',
            'heart_sound.string' => 'Heart Sound Field contains invalid data',
            'blood_pressure.string' => 'Blood Pressure Field contains invalid data',
            'pulse.string' => 'Pulse Field contains invalid data',
            'mental_altertness.string' => 'Mental Alertness Field contains invalid data',
            'glasgow_coma_scale.string' => 'Glasgow Coma Scale Field contains invalid data',
            'recommendation_status.string' => 'Recommendation Status Field contains invalid data',
            'other_examination.string' => 'Other Examination Field contains invalid data',
            'recommended_test.*.numeric' => 'Recommended Tests Field contains invalid data',
        ]);

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
            $physicalExamination->visitation_id = $visitation->id;
            $physicalExamination->added_by_id = $staff->id;
            $physicalExamination->last_updated_by_id = $staff->id;
            $physicalExamination->apex_beat = $request->apex_beat;
            $physicalExamination->blood_pressure = $request->blood_pressure;
            $physicalExamination->bmi = $request->bmi;
            $physicalExamination->heart_sound = $request->heart_sound;
            $physicalExamination->pulse = $request->pulse;
            $physicalExamination->mental_altertness = $request->mental_altertness;
            $physicalExamination->glasgow_coma_scale = $request->glasgow_coma_scale;
            $physicalExamination->recommendation_status = $request->recommendation_status;
            $physicalExamination->other_examination = $request->other_examination;

            if ($request->has('diseases')) {
                foreach ($request->diseases as $key => $disease) {
                    $physicalExamination->{'is_suffer_' . strtolower($key) . '_before'} = $disease['value'] ?? null;
                    $physicalExamination->{'is_suffer_' . strtolower($key) . '_before_remark'} = $disease['comment'] ?? null;
                }
            }

            $physicalExamination->save();

            return response()->json([
                'message' => 'Physical Examination added successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestHttpException $e) {
            Log::info('Error accepting Visitation: ' . $e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 400);
        } catch (Exception $e) {
            Log::info('Unexpected error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
