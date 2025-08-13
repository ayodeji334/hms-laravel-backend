<?php

namespace App\Http\Controllers;

use App\Models\OperationRecord;
use App\Models\Patient;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OperationRecordController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'diagnosis_before_operation' => 'required|string',
            'post_operation_diagnosis' => 'required|string',
            'procedure_carried_out' => 'required|string',
            'complications' => 'nullable|string',
            'operative_findings' => 'nullable|string',
            'anesthesia_type' => 'required|string',
            'specimens' => 'nullable|string',
            'packs' => 'nullable|string',
            'patient_id' => 'required|exists:patients,id',
            'assistant_surgeons' => 'array|nullable',
            'assistant_surgeons.*' => 'integer|exists:users,id',
            'scrub_nurses' => 'array|nullable',
            'scrub_nurses.*' => 'integer|exists:users,id',
            'operation_date' => 'required|date',
            'lead_surgeon_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $staff = Auth::user();

            // Validate existence of lead surgeon
            $leadSurgeon = User::where('id', $request->lead_surgeon_id)
                ->where('role', 'DOCTOR')
                // ->where('is_surgeon', true)
                ->first();

            if (!$leadSurgeon) {
                return response()->json([
                    'message' => 'Lead surgeon not found or not a valid surgeon',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Verify scrub nurses
            $scrubNurses = User::whereIn('id', $request->scrub_nurses)
                ->where('role', 'NURSE')
                ->get();

            if (count($scrubNurses) !== count($request->scrub_nurses)) {
                return response()->json([
                    'message' => 'One or more scrub nurses are invalid',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $assistantSurgeons = collect();
            if (!empty($request->assistant_surgeons)) {
                $assistantSurgeons = User::whereIn('id', $request->assistant_surgeons)
                    ->where('role', 'DOCTOR')
                    // ->where('is_surgeon', true)
                    ->get();

                if (count($assistantSurgeons) !== count($request->assistant_surgeons)) {
                    return response()->json([
                        'message' => 'One or more assistant surgeons are invalid',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            }

            // Save operation record
            $operationNote = new OperationRecord();
            $operationNote->diagnosis_before_operation = $request->diagnosis_before_operation;
            $operationNote->post_operation_diagnosis = $request->post_operation_diagnosis;
            $operationNote->procedure_carried_out = $request->procedure_carried_out;
            $operationNote->complications = $request->complications;
            $operationNote->operative_findings = $request->operative_findings;
            $operationNote->anesthesia_type = $request->anesthesia_type;
            $operationNote->specimens = $request->specimens;
            $operationNote->packs = $request->packs;
            $operationNote->patient_id = $request->patient_id;
            $operationNote->operation_date = $request->operation_date;
            $operationNote->surgeon_id = $leadSurgeon->id;
            $operationNote->last_updated_by_id = $staff->id;
            $operationNote->added_by_id = $staff->id;
            $operationNote->save();
            // save record before calling the sync method
            $operationNote->scrubNurses()->sync($scrubNurses);
            $operationNote->assistantSurgeons()->sync($assistantSurgeons);

            return response()->json([
                'message' => 'Operation record created successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('OperationRecord creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function create(Request $request)
    // {
    //     try {
    //         $staff = Auth::user();

    //         $surgeon = User::where('role', 'DOCTOR')->where('id', $request->surgeon_id)->first();
    //         if (!$surgeon) {
    //             return response()->json([
    //                 'message' => 'The surgeon detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $patient = Patient::where('id', $request->patient_id)->first();
    //         if (!$patient) {
    //             return response()->json([
    //                 'message' => 'The patient detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $assistantSurgeon = null;
    //         if ($request->assistant_surgeon_id) {
    //             $assistantSurgeon = User::where('id', $request->assistant_surgeon_id)
    //                 ->where('role', 'DOCTOR')
    //                 ->where('is_surgeon', true)
    //                 ->first();

    //             if (!$assistantSurgeon) {
    //                 return response()->json([
    //                     'message' => 'The assistant surgeon detail not found',
    //                     'status' => 'error',
    //                     'success' => false,
    //                 ], 400);
    //             }
    //         }

    //         $anesthetist = null;
    //         if ($request->anesthetist_id) {
    //             $anesthetist = User::where('id', operator: $request->anesthetist_id)
    //                 ->where('role', 'NURSE')
    //                 ->first();

    //             if (!$anesthetist) {
    //                 return response()->json([
    //                     'message' => 'The anesthetist detail not found',
    //                     'status' => 'error',
    //                     'success' => false,
    //                 ], 400);
    //             }
    //         }

    //         $scrubNurse = User::where('id', $request->scrub_nurse_id)
    //             ->where('role', 'NURSE')
    //             ->first();

    //         if (!$scrubNurse) {
    //             return response()->json([
    //                 'message' => 'The scrub nurse detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $operationNote = new OperationRecord();
    //         $operationNote->anesthesia_type = $request->anesthesia_type;
    //         $operationNote->anesthetist_id = $anesthetist?->id;
    //         $operationNote->assistant_surgeon_id = $assistantSurgeon?->id;
    //         $operationNote->complications = $request->complications;
    //         $operationNote->diagnosis_before_operation = $request->diagnosis_before_operation;
    //         $operationNote->operative_findings = $request->operative_findings;
    //         $operationNote->packs = $request->packs;
    //         $operationNote->post_operation_diagnosis = $request->post_operation_diagnosis;
    //         $operationNote->surgeon_id = $surgeon->id;
    //         $operationNote->specimens = $request->specimens;
    //         $operationNote->scrub_nurse_id = $scrubNurse->id;
    //         $operationNote->procedure_carried_out = $request->procedure_carried_out;
    //         $operationNote->last_updated_by_id = $staff->id;
    //         $operationNote->added_by_id = $staff->id;
    //         $operationNote->patient_id = $patient->id;
    //         $operationNote->operation_date = $request->operation_date;
    //         $operationNote->save();

    //         return response()->json([
    //             'message' => 'Operational Note added successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function remove($id)
    {
        try {
            $staff = Auth::user();

            $operationNote = OperationRecord::with([
                'surgeon',
                'anesthetist',
                'assistantSurgeon',
                'scrubNurse'
            ])->find($id);

            if (!$operationNote) {
                return response()->json([
                    'message' => 'Operation Note detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $operationNote->deleted_by_id = $staff->id;
            $operationNote->save();
            $operationNote->delete();

            return response()->json([
                'message' => 'Operation Note detail deleted successfully',
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

    public function findOne($id)
    {
        try {
            $operationNote = OperationRecord::with([
                'surgeon',
                'anesthetist',
                'assistantSurgeon',
                'scrubNurse'
            ])->find($id);

            if (!$operationNote) {
                return response()->json([
                    'message' => 'Operation Note detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return response()->json([
                'message' => 'Operation Note detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $operationNote,
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

    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $status = $request->input('status');
            $q = $request->input('q');

            $queryBuilder = OperationRecord::with([
                'patient',
                'surgeon:id,name,firstname,lastname,staff_number',
                'anesthetist:id,name,firstname,lastname,staff_number',
                'addedBy:id,name,firstname,lastname,staff_number',
                'lastUpdatedBy:id,name,firstname,lastname,staff_number',
                'assistantSurgeons:id,name,firstname,lastname,staff_number',
                'scrubNurses:id,name,firstname,lastname,staff_number'
            ])
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

            if (!empty($status) && strtoupper($status) !== 'ALL') {
                $queryBuilder->where('status', $status);
            }

            if (!empty($searchQuery)) {
                $queryBuilder->where(function ($qb) use ($searchQuery) {
                    $qb->where('firstname', 'like', "%$searchQuery%")
                        ->orWhere('email', 'like', "%$searchQuery%")
                        ->orWhere('lastname', 'like', "%$searchQuery%")
                        ->orWhere('patient_reg_no', 'like', "%$searchQuery%");
                });
            }

            $operationNotes = $queryBuilder->paginate($limit);

            return response()->json([
                'message' => 'Operation records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $operationNotes
            ]);
        } catch (Exception $e) {
            report($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
                'error' => $e
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'diagnosis_before_operation' => 'required|string',
            'post_operation_diagnosis' => 'required|string',
            'procedure_carried_out' => 'required|string',
            'complications' => 'nullable|string',
            'operative_findings' => 'nullable|string',
            'anesthesia_type' => 'required|string',
            'specimens' => 'nullable|string',
            'packs' => 'nullable|string',
            'patient_id' => 'required|exists:patients,id',
            'assistant_surgeons' => 'array|nullable',
            'assistant_surgeons.*' => 'integer|exists:users,id',
            'scrub_nurses' => 'array|nullable',
            'scrub_nurses.*' => 'integer|exists:users,id',
            'operation_date' => 'required|date',
            'lead_surgeon_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $staff = Auth::user();

            $operationNote = OperationRecord::find($id);
            if (!$operationNote) {
                return response()->json([
                    'message' => 'Operation note not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Validate existence of lead surgeon
            $leadSurgeon = User::where('id', $request->lead_surgeon_id)
                ->where('role', 'DOCTOR')
                // ->where('is_surgeon', true)
                ->first();

            if (!$leadSurgeon) {
                return response()->json([
                    'message' => 'Lead surgeon not found or not a valid surgeon',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Verify scrub nurses
            $scrubNurses = User::whereIn('id', $request->scrub_nurses)
                ->where('role', 'NURSE')
                ->get();

            if (count($scrubNurses) !== count($request->scrub_nurses)) {
                return response()->json([
                    'message' => 'One or more scrub nurses are invalid',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $assistantSurgeons = collect();
            if (!empty($request->assistant_surgeons)) {
                $assistantSurgeons = User::whereIn('id', $request->assistant_surgeons)
                    ->where('role', 'DOCTOR')
                    // ->where('is_surgeon', true)
                    ->get();

                if (count($assistantSurgeons) !== count($request->assistant_surgeons)) {
                    return response()->json([
                        'message' => 'One or more assistant surgeons are invalid',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            }

            // Save operation record
            $operationNote->diagnosis_before_operation = $request->diagnosis_before_operation;
            $operationNote->post_operation_diagnosis = $request->post_operation_diagnosis;
            $operationNote->procedure_carried_out = $request->procedure_carried_out;
            $operationNote->complications = $request->complications;
            $operationNote->operative_findings = $request->operative_findings;
            $operationNote->anesthesia_type = $request->anesthesia_type;
            $operationNote->specimens = $request->specimens;
            $operationNote->packs = $request->packs;
            $operationNote->patient_id = $request->patient_id;
            $operationNote->operation_date = $request->operation_date;
            $operationNote->surgeon_id = $leadSurgeon->id;
            $operationNote->last_updated_by_id = $staff->id;
            $operationNote->added_by_id = $staff->id;
            $operationNote->save();
            // save record before calling the sync method
            $operationNote->scrubNurses()->sync($scrubNurses);
            $operationNote->assistantSurgeons()->sync($assistantSurgeons);

            return response()->json([
                'message' => 'Operation record created successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('OperationRecord creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
    // public function update(string $id, Request $request)
    // {
    //     $request->validate([]);

    //     try {
    //         $staff = Auth::user();

    //         // Validate surgeon
    //         $surgeon = User::find($request->surgeon_id);
    //         if (!$surgeon) {
    //             return response()->json([
    //                 'message' => 'The surgeon not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // Validate patient
    //         $patient = Patient::find($request->patient_id);
    //         if (!$patient) {
    //             return response()->json([
    //                 'message' => 'The patient detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // Validate assistant surgeon if provided
    //         $assistantSurgeon = null;
    //         if ($request->assistant_surgeon_id) {
    //             $assistantSurgeon = User::where('id', $request->assistant_surgeon_id)
    //                 ->where('role', 'DOCTOR')
    //                 ->where('is_surgeon', true)
    //                 ->first();

    //             if (!$assistantSurgeon) {
    //                 return response()->json([
    //                     'message' => 'The assistant surgeon detail not found',
    //                     'status' => 'error',
    //                     'success' => false,
    //                 ], 400);
    //             }
    //         }

    //         // Validate anesthetist if provided
    //         $anesthetist = null;
    //         if ($request->anesthetist_id) {
    //             $anesthetist = User::where('id', $request->anesthetist_id)
    //                 ->where('role', 'nurse')
    //                 ->first();

    //             if (!$anesthetist) {
    //                 return response()->json([
    //                     'message' => 'The anesthetist detail not found',
    //                     'status' => 'error',
    //                     'success' => false,
    //                 ], 400);
    //             }
    //         }

    //         // Validate scrub nurse
    //         $scrubNurse = User::where('id', $request->scrub_nurse_id)
    //             ->where('role', 'nurse')
    //             ->first();

    //         if (!$scrubNurse) {
    //             return response()->json([
    //                 'message' => 'The scrub nurse detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // Find operation note
    //         $operationNote = OperationRecord::find($id);
    //         if (!$operationNote) {
    //             return response()->json([
    //                 'message' => 'Operation note not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // Update operation note
    //         // $operationNote->update([
    //         //     'anesthesia_type' => $request->anesthesia_type,
    //         //     'anesthetist_id' => $anesthetist?->id,
    //         //     'assistant_surgeon_id' => $assistantSurgeon?->id,
    //         //     'complications' => $request->complications,
    //         //     'diagnosis_before_operation' => $request->diagnosis_before_operation,
    //         //     'operative_findings' => $request->operative_findings,
    //         //     'packs' => $request->packs,
    //         //     'post_operation_diagnosis' => $request->post_operation_diagnosis,
    //         //     'surgeon_id' => $surgeon->id,
    //         //     'specimens' => $request->specimens,
    //         //     'scrub_nurse_id' => $scrubNurse->id,
    //         //     'procedure_carried_out' => $request->procedure_carried_out,
    //         //     'last_updated_by_id' => $staff->id,
    //         //     'operation_date' => $request->operation_date,
    //         // ]);

    //         $operationNote->anesthesia_type = $request->anesthesia_type;
    //         $operationNote->anesthetist_id = $anesthetist?->id;
    //         $operationNote->assistant_surgeon_id = $assistantSurgeon?->id;
    //         $operationNote->complications = $request->complications;
    //         $operationNote->diagnosis_before_operation = $request->diagnosis_before_operation;
    //         $operationNote->operative_findings = $request->operative_findings;
    //         $operationNote->packs = $request->packs;
    //         $operationNote->post_operation_diagnosis = $request->post_operation_diagnosis;
    //         $operationNote->surgeon_id = $surgeon->id;
    //         $operationNote->specimens = $request->specimens;
    //         $operationNote->scrub_nurse_id = $scrubNurse->id;
    //         $operationNote->procedure_carried_out = $request->procedure_carried_out;
    //         $operationNote->last_updated_by_id = $staff->id;
    //         $operationNote->added_by_id = $staff->id;
    //         $operationNote->patient_id = $patient->id;
    //         $operationNote->operation_date = $request->operation_date;
    //         $operationNote->save();

    //         return response()->json([
    //             'message' => 'Operation Record updated successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error($e);

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }
}
