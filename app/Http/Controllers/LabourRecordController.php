<?php

namespace App\Http\Controllers;

use App\Enums\Roles;
use App\Models\LabourGraph;
use App\Models\LabourRecord;
use App\Models\LabourSummary;
use App\Models\Patient;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class LabourRecordController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'estimated_gestational_age' => 'nullable|string',
            'expected_date_delivery' => 'nullable|date_format:Y-m-d',
            'last_menstrual_period' => 'nullable|string',
            'general_condition' => 'nullable|string',
            'abdomen_fundal_height' => 'nullable|integer',
            'abdomen_fundal_lie' => 'nullable|string',
            'abdomen_fundal_position' => 'nullable|string',
            'abdomen_fundal_descent' => 'nullable|string',
            'abdomen_fundal_presentation' => 'nullable|string',
            'foetal_heart_rate' => 'nullable|string',
            'vulva_status' => 'nullable|string',
            'vagina_status' => 'nullable|string',
            'vagina_membranes' => 'nullable|string',
            'cervix_percent' => 'nullable|integer',
            'cervix_centimeter' => 'nullable|integer',
            'pelvis_sacral_curve' => 'nullable|string',
            'placenta_pervia_position' => 'nullable|string',
            'placenta_pervia_current_station' => 'nullable|string',
            'caput' => 'nullable|string',
            'moulding' => 'nullable|string',
            'pelvis_conjugate_diameter' => 'nullable|integer',
            'pelvis_centimeter' => 'nullable|integer',
            'patient_id' => 'required|integer',
            'examiner_id' => 'required|integer',
        ], [
            'estimated_gestational_age.required' => 'Estimated Gestational Age is required.',
            'estimated_gestational_age.string' => 'Estimated Gestational Age must be a string.',
            'expected_date_delivery.required' => 'Expected Date of Delivery is required.',
            'expected_date_delivery.date_format' => 'Expected Date of Delivery must be in the format YYYY-MM-DD.',
            'last_menstrual_period.required' => 'Last Menstrual Period is required.',
            'last_menstrual_period.string' => 'Last Menstrual Period must be a string.',
            'general_condition.required' => 'General Condition is required.',
            'general_condition.string' => 'General Condition must be a string.',
            'abdomen_fundal_height.required' => 'Abdomen Fundal Height is required.',
            'abdomen_fundal_height.integer' => 'Abdomen Fundal Height must be an integer.',
            'abdomen_fundal_lie.required' => 'Abdomen Fundal Lie is required.',
            'abdomen_fundal_lie.string' => 'Abdomen Fundal Lie must be a string.',
            'abdomen_fundal_position.required' => 'Abdomen Fundal Position is required.',
            'abdomen_fundal_position.string' => 'Abdomen Fundal Position must be a string.',
            'abdomen_fundal_descent.required' => 'Abdomen Fundal Descent is required.',
            'abdomen_fundal_descent.string' => 'Abdomen Fundal Descent must be a string.',
            'abdomen_fundal_presentation.required' => 'Abdomen Fundal Presentation is required.',
            'abdomen_fundal_presentation.string' => 'Abdomen Fundal Presentation must be a string.',
            'foetal_heart_rate.required' => 'Foetal Heart Rate is required.',
            'foetal_heart_rate.string' => 'Foetal Heart Rate must be a string.',
            'vulva_status.required' => 'Vulva Status is required.',
            'vulva_status.string' => 'Vulva Status must be a string.',
            'vagina_status.required' => 'Vagina Status is required.',
            'vagina_status.string' => 'Vagina Status must be a string.',
            'vagina_membranes.required' => 'Vagina Membranes is required.',
            'vagina_membranes.string' => 'Vagina Membranes must be a string.',
            'cervix_percent.required' => 'Cervix Percentage is required.',
            'cervix_percent.integer' => 'Cervix Percentage must be an integer.',
            'cervix_centimeter.required' => 'Cervix Centimeter is required.',
            'cervix_centimeter.integer' => 'Cervix Centimeter must be an integer.',
            'pelvis_sacral_curve.required' => 'Pelvis Sacral Curve is required.',
            'pelvis_sacral_curve.string' => 'Pelvis Sacral Curve must be a string.',
            'placenta_pervia_position.required' => 'Placenta Position is required.',
            'placenta_pervia_position.string' => 'Placenta Position must be a string.',
            'placenta_pervia_current_station.required' => 'Placenta Current Position is required.',
            'placenta_pervia_current_station.string' => 'Placenta Current Position must be a string.',
            'caput.required' => 'CAPUT is required.',
            'caput.string' => 'CAPUT must be a string.',
            'moulding.required' => 'Moulding is required.',
            'moulding.string' => 'Moulding must be a string.',
            'pelvis_conjugate_diameter.required' => 'Pelvis Conjugate Diameter is required.',
            'pelvis_conjugate_diameter.integer' => 'Pelvis Conjugate Diameter must be an integer.',
            'pelvis_centimeter.required' => 'Pelvis Centimeter is required'
        ]);

        try {
            $staff = Auth::user();

            $patient = Patient::find($data['patient_id']);
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $examiner = User::find($data['examiner_id']);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if (!in_array($examiner->role, ['NURSE', 'DOCTOR'])) {
                return response()->json([
                    'message' => 'Invalid examiner. Only Doctor or Nurse can be an examiner',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $labour = new LabourRecord();
            $labour->fill([
                'abdomen_fundal_descent' => $data['abdomen_fundal_descent'] ?? null,
                'abdomen_fundal_height' => $data['abdomen_fundal_height'] ?? null,
                'abdomen_fundal_lie' => $data['abdomen_fundal_lie'] ?? null,
                'abdomen_fundal_position' => $data['abdomen_fundal_position'] ?? null,
                'abdomen_fundal_presentation' => $data['abdomen_fundal_presentation'] ?? null,
                'caput' => $data['caput'] ?? null,
                'cervix_centimeter' => $data['cervix_centimeter'] ?? null,
                'cervix_percent' => $data['cervix_percent'] ?? null,
                'estimated_gestational_age' => $data['estimated_gestational_age'] ?? null,
                'expected_date_delivery' => $data['expected_date_delivery'] ?? null,
                'foetal_heart_rate' => $data['foetal_heart_rate'] ?? null,
                'general_condititon' => $data['general_condititon'] ?? null,
                'last_menstrual_period' => $data['last_menstrual_period'] ?? null,
                'moulding' => $data['moulding'] ?? null,
                'pelvis_centimeter' => $data['pelvis_centimeter'] ?? null,
                'pelvis_conjugate_diameter' => $data['pelvis_conjugate_diameter'] ?? null,
                'pelvis_sacral_curve' => $data['pelvis_sacral_curve'] ?? null,
                'placenta_pervia_current_station' => $data['placenta_pervia_current_station'] ?? null,
                'placenta_pervia_position' => $data['placenta_pervia_position'] ?? null,
                'vagina_membranes' => $data['vagina_membranes'] ?? null,
                'vagina_status' => $data['vagina_status'] ?? null,
                'vulva_status' => $data['vulva_status'] ?? null,
            ]);
            $labour->examiner()->associate($examiner);
            $labour->lastUpdatedBy()->associate($staff);
            $labour->addedBy()->associate($staff);
            $labour->patient()->associate($patient);
            $labour->save();

            return response()->json([
                'message' => 'Labour summary created successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $search = $request->input('q');

            $query = LabourRecord::with(['patient', 'examiner', 'lastUpdatedBy', 'summary']);

            if (!empty($search)) {
                $query->whereHas('patient', function ($q) use ($search) {
                    $q->where('firstname', $search)
                        ->orWhere('lastname', $search)
                        ->orWhere('phone_number', $search)
                        ->orWhere('patient_id', $search);
                });
            }

            $records = $query
                ->orderBy('updated_at', 'desc')
                ->paginate($limit);

            return response()->json([
                'message' => 'Records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $records
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

    public function findOne($id)
    {
        try {
            $labourRecord = LabourRecord::with([
                'summary.supervisor',
                'summary.lastUpdatedBy',
                'progressions.addedBy',
                // 'patient.profilePicture',
                'patient.anteNatalRecords',
                'addedBy',
                'examiner',
                'lastUpdatedBy'
            ])->find($id);

            if (!$labourRecord) {
                return response()->json([
                    'message' => 'Labour Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'Labour record fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $labourRecord,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            report("okay");

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function update($id, Request $request)
    {
        $request->validate([
            'estimated_gestational_age' => 'nullable|string',
            'expected_date_delivery' => 'nullable|date_format:Y-m-d',
            'last_menstrual_period' => 'nullable|string',
            'general_condition' => 'nullable|string',
            'abdomen_fundal_height' => 'nullable|integer',
            'abdomen_fundal_lie' => 'nullable|string',
            'abdomen_fundal_position' => 'nullable|string',
            'abdomen_fundal_descent' => 'nullable|string',
            'abdomen_fundal_presentation' => 'nullable|string',
            'foetal_heart_rate' => 'nullable|string',
            'vulva_status' => 'nullable|string',
            'vagina_status' => 'nullable|string',
            'vagina_membranes' => 'nullable|string',
            'cervix_percent' => 'nullable|integer',
            'cervix_centimeter' => 'nullable|integer',
            'pelvis_sacral_curve' => 'nullable|string',
            'placenta_pervia_position' => 'nullable|string',
            'placenta_pervia_current_station' => 'nullable|string',
            'caput' => 'nullable|string',
            'moulding' => 'nullable|string',
            'pelvis_conjugate_diameter' => 'nullable|integer',
            'pelvis_centimeter' => 'nullable|integer',
            'patient_id' => 'required|integer',
            'examiner_id' => 'required|integer',
        ], [
            'estimated_gestational_age.required' => 'Estimated Gestational Age is required.',
            'estimated_gestational_age.string' => 'Estimated Gestational Age must be a string.',
            'expected_date_delivery.required' => 'Expected Date of Delivery is required.',
            'expected_date_delivery.date_format' => 'Expected Date of Delivery must be in the format YYYY-MM-DD.',
            'last_menstrual_period.required' => 'Last Menstrual Period is required.',
            'last_menstrual_period.string' => 'Last Menstrual Period must be a string.',
            'general_condition.required' => 'General Condition is required.',
            'general_condition.string' => 'General Condition must be a string.',
            'abdomen_fundal_height.required' => 'Abdomen Fundal Height is required.',
            'abdomen_fundal_height.integer' => 'Abdomen Fundal Height must be an integer.',
            'abdomen_fundal_lie.required' => 'Abdomen Fundal Lie is required.',
            'abdomen_fundal_lie.string' => 'Abdomen Fundal Lie must be a string.',
            'abdomen_fundal_position.required' => 'Abdomen Fundal Position is required.',
            'abdomen_fundal_position.string' => 'Abdomen Fundal Position must be a string.',
            'abdomen_fundal_descent.required' => 'Abdomen Fundal Descent is required.',
            'abdomen_fundal_descent.string' => 'Abdomen Fundal Descent must be a string.',
            'abdomen_fundal_presentation.required' => 'Abdomen Fundal Presentation is required.',
            'abdomen_fundal_presentation.string' => 'Abdomen Fundal Presentation must be a string.',
            'foetal_heart_rate.required' => 'Foetal Heart Rate is required.',
            'foetal_heart_rate.string' => 'Foetal Heart Rate must be a string.',
            'vulva_status.required' => 'Vulva Status is required.',
            'vulva_status.string' => 'Vulva Status must be a string.',
            'vagina_status.required' => 'Vagina Status is required.',
            'vagina_status.string' => 'Vagina Status must be a string.',
            'vagina_membranes.required' => 'Vagina Membranes is required.',
            'vagina_membranes.string' => 'Vagina Membranes must be a string.',
            'cervix_percent.required' => 'Cervix Percentage is required.',
            'cervix_percent.integer' => 'Cervix Percentage must be an integer.',
            'cervix_centimeter.required' => 'Cervix Centimeter is required.',
            'cervix_centimeter.integer' => 'Cervix Centimeter must be an integer.',
            'pelvis_sacral_curve.required' => 'Pelvis Sacral Curve is required.',
            'pelvis_sacral_curve.string' => 'Pelvis Sacral Curve must be a string.',
            'placenta_pervia_position.required' => 'Placenta Position is required.',
            'placenta_pervia_position.string' => 'Placenta Position must be a string.',
            'placenta_pervia_current_station.required' => 'Placenta Current Position is required.',
            'placenta_pervia_current_station.string' => 'Placenta Current Position must be a string.',
            'caput.required' => 'CAPUT is required.',
            'caput.string' => 'CAPUT must be a string.',
            'moulding.required' => 'Moulding is required.',
            'moulding.string' => 'Moulding must be a string.',
            'pelvis_conjugate_diameter.required' => 'Pelvis Conjugate Diameter is required.',
            'pelvis_conjugate_diameter.integer' => 'Pelvis Conjugate Diameter must be an integer.',
            'pelvis_centimeter.required' => 'Pelvis Centimeter is required'
        ]);

        try {
            $labourRecord = LabourRecord::find($id);

            if (!$labourRecord) {
                return response()->json([
                    'message' => 'Labour Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $examiner = User::find($request->examiner_id);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $patient = Patient::find($request['patient_id']);
            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if (!in_array($examiner->role, ['NURSE', 'DOCTOR'])) {
                return response()->json([
                    'message' => 'Invalid examiner. Only Doctor or Nurse can be an examiner',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $labourRecord->fill(
                [
                    'abdomen_fundal_descent' => $request['abdomen_fundal_descent'] ?? null,
                    'abdomen_fundal_height' => $request['abdomen_fundal_height'] ?? null,
                    'abdomen_fundal_lie' => $request['abdomen_fundal_lie'] ?? null,
                    'abdomen_fundal_position' => $request['abdomen_fundal_position'] ?? null,
                    'abdomen_fundal_presentation' => $request['abdomen_fundal_presentation'] ?? null,
                    'caput' => $request['caput'] ?? null,
                    'cervix_centimeter' => $request['cervix_centimeter'] ?? null,
                    'cervix_percent' => $request['cervix_percent'] ?? null,
                    'estimated_gestational_age' => $request['estimated_gestational_age'] ?? null,
                    'expected_date_delivery' => $request['expected_date_delivery'] ?? null,
                    'foetal_heart_rate' => $request['foetal_heart_rate'] ?? null,
                    'general_condititon' => $request['general_condititon'] ?? null,
                    'last_menstrual_period' => $request['last_menstrual_period'] ?? null,
                    'moulding' => $request['moulding'] ?? null,
                    'pelvis_centimeter' => $request['pelvis_centimeter'] ?? null,
                    'pelvis_conjugate_diameter' => $request['pelvis_conjugate_diameter'] ?? null,
                    'pelvis_sacral_curve' => $request['pelvis_sacral_curve'] ?? null,
                    'placenta_pervia_current_station' => $request['placenta_pervia_current_station'] ?? null,
                    'placenta_pervia_position' => $request['placenta_pervia_position'] ?? null,
                    'vagina_membranes' => $request['vagina_membranes'] ?? null,
                    'vagina_status' => $request['vagina_status'] ?? null,
                    'vulva_status' => $request['vulva_status'] ?? null,
                ]
            );

            $labourRecord->examiner()->associate($examiner);
            $labourRecord->patient()->associate($patient);
            $labourRecord->lastUpdatedBy()->associate(Auth::user());
            $labourRecord->save();

            return response()->json([
                'message' => 'Labour record updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function deleteProgression($id)
    {
        try {
            $graph = LabourGraph::find($id);

            if (!$graph) {
                return response()->json([
                    'message' => 'Labour Graph Progression Detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $graph->delete();

            return response()->json([
                'message' => 'Labour record deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function createProgression(
        Request $createProgressionDto,
        $id
    ) {
        try {
            $staff = Auth::user();

            $labour = LabourRecord::find($id);
            if (!$labour) {
                return response()->json([
                    'message' => 'Labour Record detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $graph = new LabourGraph();
            $graph->moulding = $createProgressionDto->moulding;
            $graph->caput = $createProgressionDto->caput;
            $graph->maternal_blood_pulse = $createProgressionDto->maternal_blood_pulse;
            $graph->maternal_pulse = $createProgressionDto->maternal_pulse;
            $graph->addedBy()->associate($staff);
            $graph->labour()->associate($labour);
            $graph->time = $createProgressionDto->time;
            $graph->cervical_dilation = $createProgressionDto->cervical_dilation;
            $graph->fetal_heart_rate = $createProgressionDto->fetal_heart_rate;
            $graph->oxytocin_administrations = $createProgressionDto->oxytocin_administrations;
            $graph->fluids_and_drugs = $createProgressionDto->fluids_and_drugs;
            $graph->maternal_temperature = $createProgressionDto->maternal_temperature;
            $graph->liquor = $createProgressionDto->liquor;
            $graph->position = $createProgressionDto->position;
            $graph->urine_analyses = $createProgressionDto->urine_analyses;
            $graph->uterine_contractions = $createProgressionDto->uterine_contractions;
            $graph->save();

            return response()->json([
                'message' => 'Labour progression detail added successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateProgression(
        Request $createProgressionDto,
        $id
    ) {
        $createProgressionDto->validate([
            'moulding' => ['required', 'string'],
            'liquor' => ['required', 'string'],
            'fluid_and_drugs' => ['required', 'array', 'min:1'],
            'fluid_and_drugs.*' => ['string'],
            'maternal_pulse' => ['required', 'string'],
            'time' => ['required', 'date'],
            'fetal_heart_rate' => ['required', 'string'],
            'maternal_temperature' => ['required', 'numeric'],
            'cervical_dilation' => ['required', 'string'],
            'maternal_blood_pulse' => ['required', 'string'],
            'caput' => ['required', 'string'],
            'position' => ['required', 'string'],
            'uterine_contractions' => ['required', 'array'],
            'uterine_contractions.frequency' => ['required', 'numeric'],
            'uterine_contractions.duration' => ['required', 'numeric'],
            'uterine_contractions.intensity' => ['required', Rule::in(['Weak', 'Moderate', 'Strong'])],
            'oxytocin_administrations' => ['required', 'array'],
            'oxytocin_administrations.units' => ['required', 'numeric'],
            'oxytocin_administrations.drops_per_minute' => ['required', 'numeric'],
            'urine_analyses' => ['required', 'array'],
            'urine_analyses.acetone' => ['required', 'numeric'],
            'urine_analyses.protein' => ['required', 'numeric'],
            'urine_analyses.volume' => ['required', 'numeric'],
        ], [
            'moulding.required' => 'Moulding Field is required',
            'moulding.string' => 'Moulding Field should be a string',
            'liquor.required' => 'Liquor Field is required',
            'liquor.string' => 'Liquor Field should be a string',
            'fluid_and_drugs.required' => 'Fluid and Drugs Field is required',
            'fluid_and_drugs.array' => 'Fluid and Drugs should be a list',
            'fluid_and_drugs.min' => 'Fluid and Drugs Field should not be empty',
            'fluid_and_drugs.*.string' => 'Each item in Fluid and Drugs should be a string',
            'maternal_pulse.required' => 'Maternal Pulse Field is required',
            'maternal_pulse.string' => 'Maternal Pulse Field should be a string',
            'time.required' => 'Time Field is required',
            'time.date' => 'Time Field should be a valid date',
            'fetal_heart_rate.required' => 'Fetal Heart Rate Field is required',
            'fetal_heart_rate.string' => 'Fetal Heart Rate Field should be a string',
            'maternal_temperature.required' => 'Maternal Temperature Field is required',
            'maternal_temperature.numeric' => 'Maternal Temperature Field should be a number',
            'cervical_dilation.required' => 'Cervical Dilation Field is required',
            'cervical_dilation.string' => 'Cervical Dilation Field should be a string',
            'maternal_blood_pulse.required' => 'Maternal Blood Pulse Field is required',
            'maternal_blood_pulse.string' => 'Maternal Blood Pulse Field should be a string',
            'caput.required' => 'Caput Pulse Field is required',
            'caput.string' => 'Caput Pulse Field should be a string',
            'position.required' => 'Position Field is required',
            'position.string' => 'Position Field should be a string',
            'uterine_contractions.required' => 'Uterine Contractions Field is required',
            'uterine_contractions.array' => 'Uterine Contractions should be an array',
            'uterine_contractions.frequency.required' => 'Frequency Field is required',
            'uterine_contractions.frequency.numeric' => 'Frequency Field should be a number',
            'uterine_contractions.duration.required' => 'Duration Field is required',
            'uterine_contractions.duration.numeric' => 'Duration Field should be a number',
            'uterine_contractions.intensity.required' => 'Intensity Field is required',
            'uterine_contractions.intensity.in' => 'Intensity Field should be either Weak, Moderate, or Strong',
            'oxytocin_administrations.required' => 'Oxytocin Administrations Field is required',
            'oxytocin_administrations.array' => 'Oxytocin Administrations should be an array',
            'oxytocin_administrations.units.required' => 'Units Field is required',
            'oxytocin_administrations.units.numeric' => 'Units Field should be a number',
            'oxytocin_administrations.drops_per_minute.required' => 'Drop per Minute Field is required',
            'oxytocin_administrations.drops_per_minute.numeric' => 'Drop per Minute Field should be a number',
            'urine_analyses.required' => 'Urine Analyses Field is required',
            'urine_analyses.array' => 'Urine Analyses should be an array',
            'urine_analyses.acetone.required' => 'Acetone Field is required',
            'urine_analyses.acetone.numeric' => 'Acetone Field should be a number',
            'urine_analyses.protein.required' => 'Protein Field is required',
            'urine_analyses.protein.numeric' => 'Protein Field should be a number',
            'urine_analyses.volume.required' => 'Volume Field is required',
            'urine_analyses.volume.numeric' => 'Volume Field should be a number',
        ]);
        try {
            $staff = Auth::user();

            $graph = LabourGraph::find($id);
            if (!$graph) {
                return response()->json([
                    'message' => 'Labour Record detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $graph->moulding = $createProgressionDto->moulding;
            $graph->caput = $createProgressionDto->caput;
            $graph->maternal_blood_pulse = $createProgressionDto->maternal_blood_pulse;
            $graph->maternal_pulse = $createProgressionDto->maternal_pulse;
            $graph->time = $createProgressionDto->time;
            $graph->cervical_dilation = $createProgressionDto->cervical_dilation;
            $graph->fetal_heart_rate = $createProgressionDto->fetal_heart_rate;
            $graph->oxytocin_administrations = $createProgressionDto->oxytocin_administrations;
            $graph->position = $createProgressionDto->position;
            $graph->urine_analyses = $createProgressionDto->urine_analyses;
            $graph->uterine_contractions = $createProgressionDto->uterine_contractions;
            $graph->fluids_and_drugs = $createProgressionDto->fluids_and_drugs;
            $graph->maternal_temperature = $createProgressionDto->maternal_temperature;
            $graph->liquor = $createProgressionDto->liquor;
            $graph->last_updated_by_id = $staff->id;
            $graph->save();

            return response()->json([
                'message' => 'Labour progression detail updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function remove($id)
    {
        try {
            $labourRecord = LabourRecord::find($id);

            if (!$labourRecord) {
                return response()->json([
                    'message' => 'Labour Record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $labourRecord->delete();

            return response()->json([
                'message' => 'Labour record deleted successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function createSummary(
        Request $createSummaryDto,
        $id
    ) {
        $createSummaryDto->validate([
            'indication' => 'required|string',
            'induction' => 'required|string',
            'method_of_delivery' => 'required|string',
            // 'expected_date_delivery' => 'required|date',
            'cephalic_presentation' => 'required|string',
            'breech_presentation' => 'required|string',
            'placenta_membranes' => 'required|string',
            'perineum' => 'required|string',
            'number_of_skin_sutures' => 'required|integer',
            'treatment' => 'required|string',
            'number_of_blood_loss' => 'required|integer',
            'time_date_of_delivery' => 'required|date',
            'malformation' => 'required|string',
            'infants_status' => 'required|array',
            'mother_uterus_condition' => 'required|string',
            'mother_bladder_condition' => 'required|string',
            // 'mother_blood_pressure' => 'required|string',
            'mother_temperature' => 'required|string',
            'mother_rep' => 'required|string',
            'supervisor' => 'required|integer',
            'mother_pulse' => 'required|string',
            'infants_sexes' => 'required|array',
            'infants_weights' => 'required|array',
        ], [
            'indication.required' => 'Indication field is required.',
            'indication.string' => 'Indication field must be a string.',
            'induction.required' => 'Induction field is required.',
            'induction.string' => 'Induction field must be a string.',
            'method_of_delivery.required' => 'Method of Delivery field is required.',
            'method_of_delivery.string' => 'Method of Delivery must be a string.',
            // 'expected_date_delivery.required' => 'Expected Date of Delivery field is required.',
            // 'expected_date_delivery.date' => 'Expected Date of Delivery must be a valid date.',
            'cephalic_presentation.required' => 'Cephalic Presentation field is required.',
            'cephalic_presentation.string' => 'Cephalic Presentation must be a string.',
            'breech_presentation.required' => 'Breech Presentation field is required.',
            'breech_presentation.string' => 'Breech Presentation must be a string.',
            'placenta_membranes.required' => 'Placenta and Membranes field is required.',
            'placenta_membranes.string' => 'Placenta and Membranes must be a string.',
            'perineum.required' => 'Perineum field is required.',
            'perineum.string' => 'Perineum must be a string.',
            'number_of_skin_sutures.required' => 'Number of Skin Sutures field is required.',
            'number_of_skin_sutures.integer' => 'Number of Skin Sutures must be an integer.',
            'treatment.required' => 'Treatment field is required.',
            'treatment.string' => 'Treatment must be a string.',
            'number_of_blood_loss.required' => 'Number of Blood Loss field is required.',
            'number_of_blood_loss.integer' => 'Number of Blood Loss must be an integer.',
            'time_date_of_delivery.required' => 'Time and Date of Delivery field is required.',
            'time_date_of_delivery.date' => 'Time and Date of Delivery must be a valid date.',
            'malformation.required' => 'Malformation field is required.',
            'malformation.string' => 'Malformation must be a string.',
            'infants_status.required' => 'Infant Status field is required.',
            'infants_status.array' => 'Infant Status must be an array.',
            'mother_uterus_condition.required' => 'Mother Uterus Condition field is required.',
            'mother_uterus_condition.string' => 'Mother Uterus Condition must be a string.',
            'mother_bladder_condition.required' => 'Mother Bladder Condition field is required.',
            'mother_bladder_condition.string' => 'Mother Bladder Condition must be a string.',
            // 'mother_blood_pressure.required' => 'Mother Blood Pressure field is required.',
            // 'mother_blood_pressure.string' => 'Mother Blood Pressure must be a string.',
            'mother_temperature.required' => 'Mother Temperature field is required.',
            'mother_temperature.string' => 'Mother Temperature must be a string.',
            'mother_rep.required' => 'Mother Rep field is required.',
            'mother_rep.string' => 'Mother Rep must be a string.',
            'supervisor.required' => 'Supervisor field is required.',
            'supervisor.integer' => 'Supervisor must be a valid integer.',
            'mother_pulse.required' => 'Mother Pulse field is required.',
            'mother_pulse.string' => 'Mother Pulse must be a string.',
            'infants_sexes.required' => 'Infant Sexes field is required.',
            'infants_sexes.array' => 'Infant Sexes must be an array.',
            'infants_weights.required' => 'Infant Weights field is required.',
            'infants_weights.array' => 'Infant Weights must be an array.',
        ]);

        try {
            $staff = Auth::user();
            $labour = LabourRecord::find($id);

            if (!$labour) {
                return response()->json([
                    'message' => 'Labour Record detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $examiner = User::find($createSummaryDto->supervisor);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Supervisor detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if (!in_array($examiner->role, [Roles::NURSE->value, Roles::DOCTOR->value])) {
                return response()->json([
                    'message' => 'Invalid supervisor. Only Doctor or Nurse can be a supervisor',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $summary = $labour->summary ?? new LabourSummary();
            $summary->fill([
                'indication' => $createSummaryDto->indication,
                'method_of_delivery' => $createSummaryDto->method_of_delivery,
                'induction' => $createSummaryDto->induction,
                'breech_presentation' => $createSummaryDto->breech_presentation,
                'cephalic_presentation' => $createSummaryDto->cephalic_presentation,
                // 'expected_date_delivery' => $createSummaryDto->expected_date_delivery,
                'infants_sexes' => $createSummaryDto->infants_sexes,
                'infants_weights' => $createSummaryDto->infants_weights,
                'infants_status' => $createSummaryDto->infants_status,
                'malformation' => $createSummaryDto->malformation,
                'treatment' => $createSummaryDto->treatment,
                'placenta_membranes' => $createSummaryDto->placenta_membranes,
                'perineum' => $createSummaryDto->perineum,
                'number_of_blood_loss' => $createSummaryDto->number_of_blood_loss,
                'number_of_skin_sutures' => $createSummaryDto->number_of_skin_sutures,
                'mother_uterus_condition' => $createSummaryDto->mother_uterus_condition,
                'mother_bladder_condition' => $createSummaryDto->mother_bladder_condition,
                'mother_pulse' => $createSummaryDto->mother_pulse,
                'mother_temperature' => $createSummaryDto->mother_temperature,
                'mother_rep' => $createSummaryDto->mother_rep,
                // 'mother_blood_pressure' => $createSummaryDto->mother_blood_pressure,
                'supervisor_id' => $examiner->id,
                'last_updated_by_id' => $staff->id,
                'labour_record_id' => $labour->id,
                'time_date_of_delivery' => $createSummaryDto->time_date_of_delivery,
            ]);
            $summary->save();

            return response()->json([
                'message' => 'Labour record created successfully',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateSummary(
        Request $createSummaryDto,
        $id
    ) {
        $createSummaryDto->validate([
            'indication' => 'required|string',
            'induction' => 'required|string',
            'method_of_delivery' => 'required|string',
            // 'expected_date_delivery' => 'required|date',
            'cephalic_presentation' => 'required|string',
            'breech_presentation' => 'required|string',
            'placenta_membranes' => 'required|string',
            'perineum' => 'required|string',
            'number_of_skin_sutures' => 'required|integer',
            'treatment' => 'required|string',
            'number_of_blood_loss' => 'required|integer',
            'time_date_of_delivery' => 'required|date',
            'malformation' => 'required|string',
            'infants_status' => 'required|array',
            'mother_uterus_condition' => 'required|string',
            'mother_bladder_condition' => 'required|string',
            // 'mother_blood_pressure' => 'required|string',
            'mother_temperature' => 'required|string',
            'mother_rep' => 'required|string',
            'supervisor' => 'required|uuid',
            'mother_pulse' => 'required|string',
            'infants_sexes' => 'required|array',
            'infants_weights' => 'required|array',
        ], [
            'indication.required' => 'Indication field is required.',
            'indication.string' => 'Indication field must be a string.',
            'induction.required' => 'Induction field is required.',
            'induction.string' => 'Induction field must be a string.',
            'method_of_delivery.required' => 'Method of Delivery field is required.',
            'method_of_delivery.string' => 'Method of Delivery must be a string.',
            // 'expected_date_delivery.required' => 'Expected Date of Delivery field is required.',
            // 'expected_date_delivery.date' => 'Expected Date of Delivery must be a valid date.',
            'cephalic_presentation.required' => 'Cephalic Presentation field is required.',
            'cephalic_presentation.string' => 'Cephalic Presentation must be a string.',
            'breech_presentation.required' => 'Breech Presentation field is required.',
            'breech_presentation.string' => 'Breech Presentation must be a string.',
            'placenta_membranes.required' => 'Placenta and Membranes field is required.',
            'placenta_membranes.string' => 'Placenta and Membranes must be a string.',
            'perineum.required' => 'Perineum field is required.',
            'perineum.string' => 'Perineum must be a string.',
            'number_of_skin_sutures.required' => 'Number of Skin Sutures field is required.',
            'number_of_skin_sutures.integer' => 'Number of Skin Sutures must be an integer.',
            'treatment.required' => 'Treatment field is required.',
            'treatment.string' => 'Treatment must be a string.',
            'number_of_blood_loss.required' => 'Number of Blood Loss field is required.',
            'number_of_blood_loss.integer' => 'Number of Blood Loss must be an integer.',
            'time_date_of_delivery.required' => 'Time and Date of Delivery field is required.',
            'time_date_of_delivery.date' => 'Time and Date of Delivery must be a valid date.',
            'malformation.required' => 'Malformation field is required.',
            'malformation.string' => 'Malformation must be a string.',
            'infants_status.required' => 'Infant Status field is required.',
            'infants_status.array' => 'Infant Status must be an array.',
            'mother_uterus_condition.required' => 'Mother Uterus Condition field is required.',
            'mother_uterus_condition.string' => 'Mother Uterus Condition must be a string.',
            'mother_bladder_condition.required' => 'Mother Bladder Condition field is required.',
            'mother_bladder_condition.string' => 'Mother Bladder Condition must be a string.',
            // 'mother_blood_pressure.required' => 'Mother Blood Pressure field is required.',
            // 'mother_blood_pressure.string' => 'Mother Blood Pressure must be a string.',
            'mother_temperature.required' => 'Mother Temperature field is required.',
            'mother_temperature.string' => 'Mother Temperature must be a string.',
            'mother_rep.required' => 'Mother Rep field is required.',
            'mother_rep.string' => 'Mother Rep must be a string.',
            'supervisor.required' => 'Supervisor field is required.',
            'supervisor.uuid' => 'Supervisor must be a valid UUID.',
            'mother_pulse.required' => 'Mother Pulse field is required.',
            'mother_pulse.string' => 'Mother Pulse must be a string.',
            'infants_sexes.required' => 'Infant Sexes field is required.',
            'infants_sexes.array' => 'Infant Sexes must be an array.',
            'infants_weights.required' => 'Infant Weights field is required.',
            'infants_weights.array' => 'Infant Weights must be an array.',
        ]);

        try {
            $staff = Auth::user();
            $summary = LabourSummary::find($id);

            if (!$summary) {
                return response()->json([
                    'message' => 'Labour Summary detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $examiner = User::find($createSummaryDto->supervisor);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Supervisor detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if (!in_array($examiner->role, [ROLES::NURSE, ROLES::DOCTOR])) {
                return response()->json([
                    'message' => 'Invalid supervisor. Only Doctor or Nurse can be a supervisor',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $summary->fill([
                'indication' => $createSummaryDto->indication,
                'method_of_delivery' => $createSummaryDto->method_of_delivery,
                'induction' => $createSummaryDto->induction,
                'breech_presentation' => $createSummaryDto->breech_presentation,
                'cephalic_presentation' => $createSummaryDto->cephalic_presentation,
                // 'expected_date_delivery' => $createSummaryDto->expected_date_delivery,
                'infants_sexes' => $createSummaryDto->infants_sexes,
                'infants_weights' => $createSummaryDto->infants_weights,
                'infants_status' => $createSummaryDto->infants_status,
                'malformation' => $createSummaryDto->malformation,
                'treatment' => $createSummaryDto->treatment,
                'placenta_membranes' => $createSummaryDto->placenta_membranes,
                'perineum' => $createSummaryDto->perineum,
                'number_of_blood_loss' => $createSummaryDto->number_of_blood_loss,
                'number_of_skin_sutures' => $createSummaryDto->number_of_skin_sutures,
                'mother_uterus_condition' => $createSummaryDto->mother_uterus_condition,
                'mother_bladder_condition' => $createSummaryDto->mother_bladder_condition,
                'mother_pulse' => $createSummaryDto->mother_pulse,
                'mother_temperature' => $createSummaryDto->mother_temperature,
                'mother_rep' => $createSummaryDto->mother_rep,
                // 'mother_blood_pressure' => $createSummaryDto->mother_blood_pressure,
                'supervisor_id' => $examiner->id,
                'last_updated_by_id' => $staff->id,
                'time_date_of_delivery' => $createSummaryDto->time_date_of_delivery,
            ]);
            $summary->save();

            return response()->json([
                'message' => 'Labour summary updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            report($e);

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
}
