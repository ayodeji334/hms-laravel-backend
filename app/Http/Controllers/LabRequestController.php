<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Enums\ServiceTypes;
use App\Models\DiagnosticTestResult;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\RadiologyRequest;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LabRequestController extends Controller
{
    // public function store(Request $request)
    // {
    //     $request->validate([
    //         'patient_id' => [
    //             'exclude_unless:is_patient,true',
    //             'integer'
    //         ],
    //         'test_id' => [
    //             'required',
    //             'integer',
    //         ],
    //         'treatment_id' => [
    //             'nullable',
    //             'integer',
    //         ],
    //         'payment_reference' => [
    //             'required',
    //             'string',
    //         ],
    //         'is_approval_required' => [
    //             'required',
    //             'boolean',
    //         ],
    //         'is_urgent' => [
    //             'required',
    //             'boolean',
    //         ],
    //         'is_patient' => [
    //             'required',
    //             'boolean',
    //         ],
    //         'request_date' => [
    //             'required',
    //             'date',
    //         ],
    //         'customer_name' => [
    //             'exclude_unless:is_patient,false',
    //             'string',
    //         ],
    //     ], [
    //         'patient_id.exclude_unless' => 'Please provide the patient information when applicable.',
    //         'patient_id.integer' => 'The provided patient ID must be a valid detail.',

    //         'test_id.required' => 'A test must be selected for the lab request.',
    //         'test_id.integer' => 'The test ID must be a valid detail.',

    //         'treatment_id.integer' => 'Treatment ID must be in detail format.',

    //         // 'payment_reference.required' => 'A payment reference is required to proceed.',
    //         // 'payment_reference.string' => 'Payment reference must be a string.',

    //         'is_approval_required.required' => 'Please specify whether approval is required.',
    //         'is_approval_required.boolean' => 'Approval requirement must be a boolean value.',

    //         'is_urgent.required' => 'Please specify whether the request is urgent.',
    //         'is_urgent.boolean' => 'Urgent field must be true or false.',

    //         'is_patient.required' => 'Please indicate if the person is a patient.',
    //         'is_patient.boolean' => 'Patient status must be a boolean.',

    //         'request_date.required' => 'The request date is required.',
    //         'request_date.date' => 'Request date must be a valid date format.',

    //         'customer_name.exclude_unless' => 'Customer name is required if the person is not a patient.',
    //         'customer_name.string' => 'Customer name must be a valid string.',
    //     ]);

    //     try {
    //         $staff = Auth::user();

    //         $patient = null;
    //         if ($request['is_patient']) {
    //             $patient = Patient::with('vitalSigns')->find($request['patient_id']);
    //             if (!$patient) {
    //                 return response()->json([
    //                     'message' => 'Patient detail not found',
    //                     'success' => false,
    //                     'status' => 'error',
    //                 ], 400);
    //             }
    //         }

    //         $test = Service::where('type', 'LAB-TEST')
    //             ->where('id', $request['test_id'])
    //             ->first();

    //         if (!$test) {
    //             return response()->json([
    //                 'message' => 'Test detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $payment = Payment::where('reference', $request['payment_reference'])->first();
    //         if (!$payment) {
    //             return response()->json([
    //                 'message' => 'Payment detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         Log::info($payment->service);

    //         if (!$payment->service) {
    //             return response()->json([
    //                 'message' => "Invalid Payment. It's not related to any service",
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($payment->service->type !== ServiceTypes::LAB_TEST->value) {
    //             return response()->json([
    //                 'message' => 'Payment reference is not for a lab test',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($payment->service->name !== $test->name) {
    //             return response()->json([
    //                 'message' => 'Payment reference is not for ' . strtoupper($test->name),
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($payment->request_id) {
    //             return response()->json([
    //                 'message' => 'Payment Reference already attached to another request',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $treatment = null;
    //         if (!empty($request['treatment_id'])) {
    //             $treatment = Treatment::find($request['treatment_id']);

    //             if (!$treatment) {
    //                 return response()->json(
    //                     [
    //                         'message' => 'Treatment detail not found',
    //                         'success' => false,
    //                         'status' => 'error',
    //                     ],
    //                     400
    //                 );
    //             }
    //         }

    //         $labRequest = new LabRequest();
    //         $labRequest->patient_id = $patient?->id;
    //         $labRequest->is_patient = $request['is_patient'];
    //         $labRequest->service_id = $test->id;
    //         $labRequest->priority = $request['is_urgent']
    //             ? "URGENT"
    //             : "ROUTINE";
    //         $labRequest->is_approval_required = $request['is_approval_required'];
    //         $labRequest->added_by_id = $staff->id;
    //         $labRequest->request_date = $request['request_date'];
    //         $labRequest->customer_name = $request['is_patient'] ? $patient->fullname : $request['customer_name'];
    //         $labRequest->treatment_id = $treatment?->id;
    //         $labRequest->save();

    //         // // Attach payment to request
    //         // $payment->request_id = $labRequest->id;
    //         // $payment->attached_by = $staff->id;
    //         // $payment->save();

    //         return response()->json([
    //             'message' => 'Lab Request created successfully',
    //             'success' => true,
    //             'status' => 'success',
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error($e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'success' => false,
    //             'status' => 'error',
    //         ], 500);
    //     }
    // }

    public function storeLabRequest(Request $request)
    {
        $request->validate([
            'patient_id' => ['exclude_unless:is_patient,true', 'integer'],
            'test_id' => ['required', 'integer'],
            'treatment_id' => ['nullable', 'integer'],
            'payment_reference' => ['required_without:treatment_id', 'string'],
            'is_approval_required' => ['required', 'boolean'],
            'is_urgent' => ['required', 'boolean'],
            'is_patient' => ['required', 'boolean'],
            'request_date' => ['required', 'date'],
            'customer_name' => ['exclude_unless:is_patient,false', 'string'],
        ], [
            'payment_reference.required_without' => 'Payment reference is required if there is no treatment ID.',
            // ... (rest of messages)
        ]);

        try {
            $staff = Auth::user();
            $patient = null;

            if ($request['is_patient']) {
                $patient = Patient::with('vitalSigns')->find($request['patient_id']);
                if (!$patient) {
                    return response()->json([
                        'message' => 'Patient detail not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            $test = Service::where('type', 'LAB-TEST')->find($request['test_id']);
            if (!$test) {
                return response()->json([
                    'message' => 'Test detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $treatment = null;
            if (!empty($request['treatment_id'])) {
                $treatment = Treatment::find($request['treatment_id']);
                if (!$treatment) {
                    return response()->json([
                        'message' => 'Treatment detail not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            $labRequest = new LabRequest();
            $labRequest->patient_id = $patient?->id;
            $labRequest->is_patient = $request['is_patient'];
            $labRequest->service_id = $test->id;
            $labRequest->priority = $request['is_urgent'] ? "URGENT" : "ROUTINE";
            $labRequest->is_approval_required = $request['is_approval_required'];
            $labRequest->added_by_id = $staff->id;
            $labRequest->request_date = $request['request_date'];
            $labRequest->customer_name = $request['is_patient'] ? $patient->fullname : $request['customer_name'];
            $labRequest->treatment_id = $treatment?->id;
            $labRequest->save();

            if (!$treatment) {
                // Validate and attach existing payment
                $payment = Payment::with('service')->where('reference', $request['payment_reference'])->first();

                if (!$payment) {
                    return response()->json([
                        'message' => 'Payment detail not found',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                Log::info($payment->id);

                if ($payment->type !== ServiceTypes::LAB_TEST->value) {
                    return response()->json([
                        'message' => 'Payment is not for a valid lab test service',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                // if ($payment->service->name !== $test->name) {
                //     return response()->json([
                //         'message' => 'Payment reference does not match test name: ' . strtoupper($test->name),
                //         'success' => false,
                //         'status' => 'error',
                //     ], 400);
                // }

                if ($payment->request_id) {
                    return response()->json([
                        'message' => 'Payment already used for another request',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $payment->payable_id = $labRequest->id;
                $payment->added_by_id = $staff->id;
                $payment->save();
            } else {
                // Optional: create a new Payment and link it to the lab request using morph
                $payment = new Payment();
                $payment->customer_name = $patient->full_name;
                $payment->transaction_reference = strtoupper(Str::random(10));
                $payment->amount = $test->price;
                $payment->added_by_id = $staff->id;
                $payment->patient_id = $patient->id;
                $payment->type = 'LAB-TEST';
                $payment->payable()->associate($labRequest);
                $payment->history = json_encode([
                    ['date' => now()->toDateTimeString(), 'title' => 'CREATED'],
                ]);
                $payment->save();
            }

            return response()->json([
                'message' => 'Lab Request created successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function storeRadiologyRequest(Request $request)
    {
        $request->validate([
            'test_id' => ['required', 'integer'],
            'payment_reference' => ['required_without:treatment_id', 'string'],
            'is_urgent' => ['required', 'boolean'],
            'is_patient' => ['required', 'boolean'],
            'patient_id' => ['exclude_unless:is_patient,true', 'integer'],
            'request_date' => ['required', 'date'],
            'customer_name' => ['exclude_unless:is_patient,false', 'string'],
            'treatment_id' => ['nullable', 'integer'],
            'clinical_diagnosis' => ['required', 'string'],
            'part_examined' => ['required', 'string'],
            'size_of_films' => ['required', 'string'],
            'number_of_films' => ['required', 'integer'],
        ]);

        try {
            $staff = Auth::user();
            $patient = null;

            if ($request['is_patient']) {
                $patient = Patient::find($request['patient_id']);
                if (!$patient) {
                    return response()->json(['message' => 'Patient not found', 'status' => 'error', 'success' => false], 400);
                }
            }

            $test = Service::where('type', 'RADIOLOGY-TEST')->find($request['test_id']);
            if (!$test) {
                return response()->json(['message' => 'Test not found', 'status' => 'error', 'success' => false], 400);
            }

            $treatment = null;
            if (!empty($request['treatment_id'])) {
                $treatment = Treatment::find($request['treatment_id']);
                if (!$treatment) {
                    return response()->json(['message' => 'Treatment not found', 'status' => 'error', 'success' => false], 400);
                }
            }

            $radiologyRequest = new RadiologyRequest();
            $radiologyRequest->clinical_diagnosis = $request['clinical_diagnosis'];
            $radiologyRequest->part_examined = $request['part_examined'];
            $radiologyRequest->size_of_films = $request['size_of_films'];
            $radiologyRequest->number_of_films = $request['number_of_films'];
            $radiologyRequest->service_id = $test->id;
            $radiologyRequest->added_by_id = $staff->id;
            $radiologyRequest->patient_id = $patient->id;
            $radiologyRequest->treatment_id = $treatment?->id;
            $radiologyRequest->customer_name = $request['is_patient'] ? $patient->fullname : $request['customer_name'];;
            $radiologyRequest->request_date = $request['request_date'];
            $radiologyRequest->status = 'CREATED';
            $radiologyRequest->save();

            if (!$treatment) {
                $payment = Payment::with('payable')
                    ->where('reference', $request['payment_reference'])
                    ->where('payable_type', RadiologyRequest::class)
                    ->first();

                if (!$payment) {
                    return response()->json(['message' => 'Invalid or unmatched payment for radiology test', 'status' => 'error', 'success' => false], 400);
                }

                if ($payment->is_used || $payment->status !== PaymentStatus::COMPLETED->value) {
                    return response()->json(['message' => 'Payment reference already used or mismatched with test name', 'status' => 'error', 'success' => false], 400);
                }

                $payment->payable_id = $radiologyRequest->id;
                $payment->added_by_id = $staff->id;
                $payment->is_used = true;
                $payment->save();
            } else {
                $payment = new Payment();
                $payment->customer_name = $patient->full_name;
                $payment->transaction_reference = strtoupper(Str::random(10));
                $payment->amount = $test->price;
                $payment->added_by_id = $staff->id;
                $payment->patient_id = $patient->id;
                $payment->type = 'RADIOLOGY-TEST';
                $payment->payable()->associate($radiologyRequest);
                $payment->history = json_encode([
                    ['date' => now()->toDateTimeString(), 'title' => 'CREATED'],
                ]);
                $payment->save();
            }

            return response()->json(['message' => 'Radiology Request created successfully', 'success' => true, 'status' => 'success']);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['message' => 'Something went wrong. Try again later', 'success' => false, 'status' => 'error'], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'test_id' => 'required|integer|exists:services,id',
                'is_patient' => 'required|boolean',
                'patient_id' => 'nullable|integer|exists:patients,id',
                'is_approval_required' => 'nullable|boolean',
                'request_date' => 'required|date',
                'customer_name' => 'nullable|string|max:255',
            ]);

            $labRequest = LabRequest::with(['service', 'payment', 'patient'])->find($id);

            if (!$labRequest) {
                return response()->json([
                    'message' => 'Request detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // if ($labRequest->testResult) {
            //     return response()->json([
            //         'message' => 'Cannot update the request because the result of the test has been added',
            //         'status' => 'error',
            //         'success' => false,
            //     ], 400);
            // }

            $test = Service::where('type', 'LAB-TEST')->find($validated['test_id']);
            if (!$test) {
                return response()->json([
                    'message' => 'Test detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $patient = $labRequest->patient;

            if (
                $validated['is_patient'] &&
                isset($validated['patient_id']) &&
                $validated['patient_id'] !== optional($labRequest->patient)->id
            ) {
                $patient = Patient::with('vitalSigns')->find($validated['patient_id']);

                if (!$patient) {
                    return response()->json([
                        'message' => 'Patient detail not found',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            }

            $labRequest->patient_id = $patient?->id;
            $labRequest->is_patient = $validated['is_patient'];
            $labRequest->service_id = $test->id;
            $labRequest->is_approval_required = $validated['is_approval_required'] ?? false;
            $labRequest->last_updated_by_id = Auth::id();
            $labRequest->request_date = $validated['request_date'];
            $labRequest->customer_name = $validated['is_patient']
                ? $patient->fullname
                : $validated['customer_name'];

            $labRequest->save();

            return response()->json([
                'message' => 'Lab Request updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating lab request: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateRadiology(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'test_id' => 'required|integer|exists:services,id',
                'is_patient' => 'required|boolean',
                'patient_id' => 'nullable|integer|exists:patients,id',
                'is_approval_required' => 'nullable|boolean',
                'request_date' => 'required|date',
                'customer_name' => 'nullable|string|max:255',
            ]);

            $radiologyRequest = RadiologyRequest::with(['service', 'payment', 'patient'])->find($id);

            if (!$radiologyRequest) {
                return response()->json([
                    'message' => 'Radiology request not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $test = Service::where('type', 'RADIOLOGY-TEST')->find($validated['test_id']);
            if (!$test) {
                return response()->json([
                    'message' => 'Radiology test detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $patient = $radiologyRequest->patient;

            if (
                $validated['is_patient'] &&
                isset($validated['patient_id']) &&
                $validated['patient_id'] !== optional($radiologyRequest->patient)->id
            ) {
                $patient = Patient::with('vitalSigns')->find($validated['patient_id']);

                if (!$patient) {
                    return response()->json([
                        'message' => 'Patient detail not found',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            }

            $radiologyRequest->patient_id = $patient?->id;
            $radiologyRequest->is_patient = $validated['is_patient'];
            $radiologyRequest->service_id = $test->id;
            $radiologyRequest->is_approval_required = $validated['is_approval_required'] ?? false;
            $radiologyRequest->last_updated_by_id = Auth::id();
            $radiologyRequest->request_date = $validated['request_date'];
            $radiologyRequest->customer_name = $validated['is_patient']
                ? $patient->fullname
                : $validated['customer_name'];

            $radiologyRequest->save();

            return response()->json([
                'message' => 'Radiology request updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating radiology request: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function createResult(Request $request, $id)
    {
        $request->validate([
            'type' => ['required', 'in:LAB,RADIOLOGY'],
            'result_carried_out_by' => ['required', 'integer'],
            'result_date' => ['required', 'date'],
            'result_details' => ['required', 'array'],
            'is_save_as_draft' => ['required', 'boolean'],
        ]);

        try {
            $staff = Auth::user();
            $type = strtoupper($request->input('type'));

            $requestModel = $type === 'LAB' ? LabRequest::class : RadiologyRequest::class;

            /** @var LabRequest|RadiologyRequest|null $testRequest */
            $testRequest = $requestModel::with(['payment', 'patient', 'testResult', 'service'])->find($id);

            if (!$testRequest) {
                return response()->json([
                    'message' => "{$type} request not found",
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($testRequest->testResult) {
                return response()->json([
                    'message' => 'Result already exists for this request.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            Log::info(($testRequest->payment->id));

            if (!$testRequest->payment || $testRequest->payment->status !== 'COMPLETED') {
                return response()->json([
                    'message' => 'Payment not found or not completed.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $examiner = User::find($request['result_carried_out_by']);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner not found.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $result = new DiagnosticTestResult();
            $result->added_by_id = $staff->id;
            $result->patient_id = $testRequest->patient_id;
            $result->result_carried_out_by_id = $examiner->id;
            $result->result_date = $request['result_date'];
            $result->result_details = $request['result_details'];
            $result->test_id = $testRequest->service_id;
            $result->request_id = $testRequest->id;
            $result->resultable_id = $testRequest->id;
            $result->resultable_type = $requestModel; // polymorphic link
            $result->is_save_as_draft = $request['is_save_as_draft'];
            $result->save();

            return response()->json([
                'message' => "{$type} test result created successfully",
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again later.',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }



    // public function createResult(Request $request, $id)
    // {
    //     try {
    //         $staff = Auth::user();

    //         $labrequest = LabRequest::with(['payment', 'patient', 'testResult', 'service'])->find($id);
    //         if (!$labrequest) {
    //             return response()->json([
    //                 'message' => 'Request detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($labrequest->testResult) {
    //             return response()->json([
    //                 'message' => 'Result already added. You cannot create another result',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if (!$labrequest->payment || $labrequest->payment->status !== 'COMPLETED') {
    //             return response()->json([
    //                 'message' => 'Payment is either not found or not completed. Result cannot be added.',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $examiner = User::find($request['result_carried_out_by']);
    //         if (!$examiner) {
    //             return response()->json([
    //                 'message' => 'Examiner detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $result = new DiagnosticTestResult();
    //         $result->added_by_id = $staff->id;
    //         $result->patient_id = $labrequest->patient_id;
    //         $result->result_carried_out_by_id = $examiner->id;
    //         $result->result_date = $request['result_date'];
    //         $result->result_details = $request['result_details'];
    //         $result->test_id = $labrequest->service_id;
    //         $result->request_id = $labrequest->id;
    //         $result->is_save_as_draft = $request['is_save_as_draft'];
    //         $result->save();

    //         return response()->json([
    //             'message' => 'Test result created successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error($e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong',
    //             'success' => false,
    //             'status' => 'error',
    //         ], 500);
    //     }
    // }

    // public function createResult(Request $request, $id)
    // {
    //     try {
    //         $staff = Auth::user();
    //         $labrequest = LabRequest::with(['payment', 'patient', 'testResult', 'service'])->find($id);

    //         if (!$labrequest) {
    //             return response()->json([
    //                 'message' => 'Request detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         if ($labrequest->testResult) {
    //             return response()->json([
    //                 'message' => 'Result already added. You cannot create another result',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }
    //         $examiner = User::find($request['result_carried_out_by']);
    //         if (!$examiner) {
    //             return response()->json([
    //                 'message' => 'Examiner detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $result = new DiagnosticTestResult();
    //         $result->added_by_id = $staff->id;
    //         $result->patient_id = $labrequest->patient_id;
    //         $result->result_carried_out_by_id = $examiner->id;
    //         $result->result_date = $request['result_date'];
    //         $result->result_details = $request['result_details'];
    //         $result->test_id = $labrequest->service_id;
    //         $result->request_id = $labrequest->id;
    //         $result->is_save_as_draft = $request['is_save_as_draft'];
    //         $result->save();

    //         return [
    //             'message' => 'Test result created successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ];
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());
    //         return response()->json([
    //             'message' => 'Something went wrong',
    //             'success' => false,
    //             'status' => 'error',
    //         ], 500);
    //     }
    // }

    public function updateResult(Request $request, $id)
    {
        try {
            $staff = Auth::user();

            $result = DiagnosticTestResult::find($id);
            if (!$result) {
                return response()->json([
                    'message' => 'Result detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $examiner = User::find($request['result_carried_out_by']);
            if (!$examiner) {
                return response()->json([
                    'message' => 'Examiner detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $result->result_carried_out_by_id = $examiner->id;
            $result->result_date = $request['result_date'];
            $result->result_details = $request['result_details'];
            $result->last_updated_by_id = $staff->id;
            $result->is_save_as_draft = $request['is_save_as_draft'];
            $result->save();

            return [
                'message' => 'Test result updated successfully',
                'status' => 'success',
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findOne(string $id)
    {
        try {
            $labRequest = LabRequest::with([
                'approvedBy',
                'patient',
                'service',
                'payment'
            ])->find($id);

            if (!$labRequest) {
                return response()->json([
                    'message' => 'Request not found',
                    'success' => false,
                    'status' => 'error',
                ]);
            }

            return [
                'message' => 'Lab request records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $labRequest,
            ];
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function destory(string $id)
    {
        try {
            $labRequest = LabRequest::with([
                'approvedBy',
                'patient',
                'service',
                'payment'
            ])->find($id);

            if (!$labRequest) {
                return response()->json([
                    'message' => 'Request not found',
                    'success' => false,
                    'status' => 'error',
                ]);
            }

            $labRequest->delete();

            return [
                'message' => 'Lab request records deleted successfully',
                'status' => 'success',
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findAllLabRequests(Request $request)
    {
        try {
            // $type = strtoupper($request->input('type', ''));
            $searchQuery = $request->input('q', '');

            $query = LabRequest::with([
                'patient',
                'payment:id,payable_id,status,transaction_reference',
                'service.resultTemplate.categories.tables.category',
                'service.resultTemplate.tables.rows',
                'service.resultTemplate.tables.columns',
                'service.resultTemplate.tables.rowCategories.rows',
                'service.resultTemplate.categories.tables.rows',
                'service.resultTemplate.categories.tables.columns',
                'service.resultTemplate.categories.tables.rowCategories.rows',
                'addedBy',
                'testResult.test',
                'testResult.patient',
                'testResult.addedBy',
                'testResult.resultCarriedOutBy',
                'treatment:id,diagnosis',
                'treatment.createdBy:id,firstname,lastname'
            ]);

            // if ($type === 'RESULT-AVAILABLE') {
            //     $query->whereHas('testResult');
            // } elseif ($type === 'RESULT-NOT-AVAILABLE') {
            //     $query->whereDoesntHave('testResult');
            // }

            if (!empty($searchQuery)) {
                $query->whereHas('patient', function ($q) use ($searchQuery) {
                    $q->where('firstname', 'like', "%{$searchQuery}%")
                        ->orWhere('lastname', 'like', "%{$searchQuery}%")
                        ->orWhere('patient_reg_no', '=', $searchQuery);
                });
            }

            $labRequests = $query->orderByDesc('updated_at')->paginate();

            return response()->json([
                'message' => 'Lab Request fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $labRequests
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching lab requests: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function findAllRadiologyRequests(Request $request)
    {
        try {
            $searchQuery = $request->input('q', '');

            $query = RadiologyRequest::with([
                'patient',
                'payment:id,payable_id,status,transaction_reference',
                'service.resultTemplate.categories.tables.category',
                'service.resultTemplate.tables.rows',
                'service.resultTemplate.tables.columns',
                'service.resultTemplate.tables.rowCategories.rows',
                'service.resultTemplate.categories.tables.rows',
                'service.resultTemplate.categories.tables.columns',
                'service.resultTemplate.categories.tables.rowCategories.rows',
                'addedBy',
                'testResult.test',
                'testResult.patient',
                'testResult.addedBy',
                'testResult.resultCarriedOutBy',
                'treatment:id,diagnosis',
                'treatment.createdBy:id,firstname,lastname'
            ]);

            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('customer_name', 'like', "%{$searchQuery}%");
                });
            }

            $radiologyRequests = $query->orderByDesc('updated_at')->paginate();

            return response()->json([
                'message' => 'Radiology Request fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $radiologyRequests,
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching radiology requests: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function getReport()
    {
        try {
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $elevenMonthsAgo = Carbon::now()->subMonths(11);

            $totalRequests = LabRequest::count();
            $totalUrgentRequests = LabRequest::where('priority', 'URGENT')->count();
            $lastFiveRequestWithoutResult = LabRequest::with(['testResult', 'payment', 'addedBy', 'service'])
                ->whereDoesntHave('testResult')
                ->latest('updated_at')
                ->limit(5)
                ->get();

            $requests = DB::table('lab_requests as request')
                ->leftJoin('diagnostic_tests as result', 'request.id', '=', 'result.request_id')
                ->selectRaw('
                MONTH(request.created_at) as month,
                YEAR(request.created_at) as year,
                CASE 
                    WHEN result.id IS NULL THEN "PENDING"
                    ELSE "COMPLETED" 
                END as statusGroup
            ')
                ->whereBetween('request.created_at', [$elevenMonthsAgo, $yesterday])
                ->groupBy('year', 'month', 'statusGroup', 'request.created_at')
                ->orderByDesc('request.created_at')
                ->get();

            $totalRequestsWithResult = LabRequest::whereHas('testResult')->count();

            $chartData = $this->formatData(
                collect($requests)->toArray(),
                $today,
                $elevenMonthsAgo
            );

            $allUrgentRequests = $totalUrgentRequests;
            $allRoutineRequests = $totalRequests - $totalUrgentRequests;
            $allRequestsWithResult = $totalRequestsWithResult;
            $allRequestsWithoutResult = $totalRequests - $totalRequestsWithResult;
            $allRequests = $totalRequests;

            return response()->json([
                'message' => 'Laboratory report detail Fetched Successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'total_test_requests' => $allRequests,
                    'total_test_requests_with_result' => $allRequestsWithResult,
                    'total_test_requests_without_result' => $allRequestsWithoutResult,
                    'recent_requests_without_result' => array_merge(
                        $lastFiveRequestWithoutResult->toArray(),
                        // $xRayReportData['data']['recent_requests_without_result']
                    ),
                    'total_urgent_requests' => $allUrgentRequests,
                    'total_routine_requests' => $allRoutineRequests,
                    'chart_data' => $chartData,
                ]
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    protected function formatData(array $requests, Carbon $today, Carbon $elevenMonthsAgo): array
    {
        try {
            $months = $this->getMonthsBetweenTodayAndElevenMonthsAgo($today, $elevenMonthsAgo);

            $formattedData = [];

            foreach ($months as $monthInfo) {
                $month = $monthInfo['month'];
                $year = $monthInfo['year'];

                $pending = collect($requests)->filter(function ($item) use ($month, $year) {
                    return (int) $item->month === ($month + 1)
                        && (int) $item->year === $year
                        && $item->statusGroup === 'PENDING';
                })->count();

                $completed = collect($requests)->filter(function ($item) use ($month, $year) {
                    return (int) $item->month === ($month + 1)
                        && (int) $item->year === $year
                        && $item->statusGroup === 'COMPLETED';
                })->count();

                $formattedData[] = [
                    'month' => $month,
                    'year' => $year,
                    'pending' => $pending,
                    'completed' => $completed,
                ];
            }

            return $formattedData;
        } catch (Exception $e) {
            Log::info($e->getMessage());
            throw $e;
        }
    }

    protected function getMonthsBetweenTodayAndElevenMonthsAgo(Carbon $today, Carbon $elevenMonthsAgo): array
    {
        $monthsAndYears = [];
        $startDate = $today->copy();
        $endDate = $elevenMonthsAgo->copy();

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $currentDate = $startDate->copy()->startOfMonth();

        while ($currentDate->lte($endDate)) {
            $monthsAndYears[] = [
                'year' => (int) $currentDate->format('Y'),
                'month' => (int) $currentDate->format('n') - 1,
            ];

            $currentDate->addMonth();
        }

        return $monthsAndYears;
    }

    // protected function formatData(array $rawRequests, Carbon $today, Carbon $startDate): array
    // {
    //     $dataMap = [];

    //     // Initialize the map with default 12 months of data
    //     $current = $startDate->copy()->startOfMonth();
    //     $end = $today->copy()->startOfMonth();

    //     while ($current->lte($end)) {
    //         $key = $current->format('Y-m');
    //         $dataMap[$key] = [
    //             'month' => $current->format('F'),
    //             'year' => $current->year,
    //             'PENDING' => 0,
    //             'COMPLETED' => 0,
    //         ];
    //         $current->addMonth();
    //     }

    //     foreach ($rawRequests as $record) {
    //         $month = str_pad($record['month'], 2, '0', STR_PAD_LEFT);
    //         $key = "{$record['year']}-{$month}";
    //         $status = $record['statusGroup'] ?? 'PENDING';

    //         if (isset($dataMap[$key])) {
    //             $dataMap[$key][$status]++;
    //         }
    //     }

    //     return array_values($dataMap);
    // }
}
