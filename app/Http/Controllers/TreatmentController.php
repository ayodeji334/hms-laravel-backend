<?php

namespace App\Http\Controllers;

use App\Enums\TreatmentStatus;
use App\Models\Admission;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Product;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\TreatmentItem;
use App\Models\Visitation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class TreatmentController extends Controller
{
    protected $admissionController;
    protected $paymentController;

    public function __construct(AdmissionController $admissionController, PaymentController $paymentController)
    {
        $this->admissionController = $admissionController;
        $this->paymentController = $paymentController;
    }

    public function create(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'diagnosis' => ['nullable', 'string'],
            'treatment_date' => ['required', 'date'],
            'treatment_end_date' => ['nullable', 'date'],
            'treatment_type' => ['required', 'string'],
            'visitation_id' => ['nullable', 'exists:visitations,id'],
        ]);

        try {
            $staff = Auth::user();
            $patient = Patient::find($validated['patient_id']);

            if (!$patient) {
                return response()->json([
                    'message' => 'Patient detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Check if the patient is admitted
            $admission = Admission::where('patient_id', $patient->id)
                ->where('status', 'ACTIVE')
                ->first();

            $visitation = null;
            if (!empty($validated['visitation_id'])) {
                $visitation = Visitation::find($validated['visitation_id']);

                if (!$visitation) {
                    return response()->json([
                        'message' => 'Visitation detail not found',
                        'status' => 'error',
                        'success' => false,
                    ]);
                }
            }

            $treatment = new Treatment();
            $treatment->diagnosis = $validated['diagnosis'] ?? null;
            $treatment->treatment_date = $validated['treatment_date'];
            $treatment->treatment_end_date = $validated['treatment_end_date'] ?? null;
            $treatment->treatment_type = $validated['treatment_type'];
            $treatment->status = TreatmentStatus::IN_PROGRESS;

            Log::info($visitation);

            if ($admission) {
                $treatment->admission()->associate($admission);
            }

            if ($visitation) {
                $treatment->visitation()->associate($visitation);
            }

            $treatment->patient()->associate($patient);
            $treatment->createdBy()->associate($staff);
            $treatment->treatedBy()->associate($staff);
            $treatment->lastUpdatedBy()->associate($staff);
            $treatment->save();

            return response()->json([
                'message' => 'Treatment record added successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error creating treatment: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'exists:patients,id'],
            'diagnosis' => ['nullable', 'string'],
            'treatment_date' => ['required', 'date'],
            'treatment_end_date' => ['nullable', 'date'],
            'treatment_type' => ['required', 'string'],
            'visitation_id' => ['nullable', 'exists:visitations,id'],
        ]);

        try {
            $staff = Auth::user();

            // Check if the patient exists
            $patient = Patient::with('vitalSigns')->find($validated['patient_id']);

            if (!$patient) {
                return response()->json([
                    'message' => 'Patient not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Check if the patient is admitted
            $admission = $this->admissionController->checkIfPatientIsAdmitted($patient->id) ?? null;

            Log::info($admission);

            $treatmentRecord = Treatment::find($id);
            if (!$treatmentRecord) {
                return response()->json([
                    'message' => 'Treatment record not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Update treatment record
            $treatmentRecord->update([
                'diagnosis' => $validated['diagnosis'],
                'patient_id' => $patient->id,
                'last_updated_by_id' => $staff->id,
                'treatment_date' => $validated['treatment_date'],
                'treatment_end_date' => $validated['treatment_end_date'] ?? null,
                'treatment_type' => $validated['treatment_type'],
                'admission_id' => $admission ? $admission->id : null,
            ]);

            return response()->json([
                'message' => 'Treatment record updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findAll(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $status = strtoupper($request->input('status', ''));
            $queryText = $request->input('q', '');

            $query = Treatment::with(['patient', 'createdBy']);

            if (!empty($status) && strtoupper($status) !== 'ALL') {
                $query->where('status', $status);
            }

            if (!empty($queryText)) {
                $query->whereHas('patient', function ($subQuery) use ($queryText) {
                    $subQuery->where('firstname', 'LIKE', "%{$queryText}%")
                        ->orWhere('lastname', 'LIKE', "%{$queryText}%")
                        ->orWhere('patient_reg_no', 'LIKE', "%{$queryText}%");
                });
            }

            $paginated = $query->orderByDesc('updated_at')->paginate($limit);

            return response()->json([
                'message' => 'Prescriptions fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $paginated
            ]);
        } catch (BadRequestException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], 400);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $staffId = Auth::user()->id;
            $treatment = Treatment::with('createdBy')->find($id);

            if (!$treatment) {
                return response()->json([
                    'message' => 'Treatment not find',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            Log::info($treatment->created_by_id . $staffId);

            if ($treatment->created_by_id != $staffId) {
                return response()->json([
                    'message' => 'You do not have the authorized permission to delete the Treatment',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            $treatment->delete();

            return response()->json([
                'message' => 'Treatment deleted successfully',
                'status' => 'success',
                "success" => true
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                "success" => false
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $treatment = Treatment::with(['createdBy.assignedBranch', 'notes.createdBy', 'prescriptions.requestedBy', 'prescriptions.items',  'patient', 'tests.addedBy', 'tests.service', 'items.product'])->find($id);

            if (!$treatment) {
                return response()->json([
                    'message' => 'Treatment not find',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            return response()->json([
                'message' => 'Treatment deleted successfully',
                'status' => 'success',
                "success" => true,
                'data' => $treatment
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                "success" => false
            ], 500);
        }
    }

    public function cancel($id)
    {
        return $this->toggleStatus($id, 'CANCELED');
    }

    public function complete($id)
    {
        return $this->toggleStatus($id, 'COMPLETED');
    }

    public function inProgress($id)
    {
        return $this->toggleStatus($id, 'IN_PROGRESS');
    }

    private function toggleStatus($id, $status)
    {
        DB::beginTransaction();

        try {
            $staff = Auth::user();

            $treatment = Treatment::with(['items.product', 'patient.wallet', 'payments'])->find($id);

            if (!$treatment) {
                return response()->json([
                    'message' => 'Treatment detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status === 'CANCELED' && $treatment->status === 'CANCELED') {
                return response()->json([
                    'message' => 'Treatment record already marked as cancelled',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status === 'IN_PROGRESS' && $treatment->status === 'IN_PROGRESS') {
                return response()->json([
                    'message' => 'Treatment record already marked as in progress',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($status === 'COMPLETED' && $treatment->status === 'COMPLETED') {
                return response()->json([
                    'message' => 'Treatment record already marked as completed',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $consultationService = Service::where('type', 'CONSULTATION_FEE')->first();

            $totalPrice = collect($treatment->items)->map(function ($item) {
                return $item->product && $item->product->unit_price
                    ? floatval($item->product->unit_price) * $item->quantity
                    : 0;
            })->sum();

            if ($consultationService) {
                $totalPrice += floatval($consultationService->price);
            }

            if ($status === 'COMPLETED') {
                $this->paymentController->createTreatmentPayment($treatment, $totalPrice);
            }

            if ($status === 'IN_PROGRESS') {
                if ($treatment->patient && $treatment->patient->wallet) {
                    $wallet = $treatment->patient->wallet;
                    $wallet->outstanding_balance = floatval($wallet->outstanding_balance) - $totalPrice;
                    $wallet->save();
                }
            }

            $treatment->status = $status;
            $treatment->last_updated_by_id = $staff->id;
            $treatment->save();

            DB::commit();

            return response()->json([
                'message' => 'Treatment record status changed successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function addItems(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|numeric|not_in:NaN,INF,-INF',
            'items.*.product_id' => 'required|integer',
        ], [
            'items.required' => 'Please provide at least one treatment item.',
            'items.array' => 'The treatment items should be an array.',
            'items.min' => 'At least one item must be specified.',
            'items.*.quantity.required' => 'Each treatment item must have a quantity.',
            'items.*.quantity.numeric' => 'Quantity must be a valid number.',
            'items.*.quantity.not_in' => 'Quantity must be a real number and not invalid.',
            'items.*.product_id.required' => 'Each item must include a product reference.',
            'items.*.product_id.integer' => 'Product ID must be a valid detail.',
        ]);

        try {
            $staff = Auth::user();
            $treatment = Treatment::with(['notes', 'items'])->find($id);

            if (!$treatment) {
                return response()->json([
                    'message' => 'Treatment record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($treatment->status !== TreatmentStatus::IN_PROGRESS->value) {
                return response()->json([
                    'message' => 'Cannot add new items because the treatment is not in-progress',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            foreach ($request['items'] as $currentItem) {
                $product = Product::find($currentItem['product_id']);

                if (!$product) {
                    return response()->json([
                        'message' => "Product with ID {$currentItem['product_id']} not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                TreatmentItem::create([
                    'treatment_id' => $treatment->id,
                    'product_id' => $product->id,
                    'quantity' => $currentItem['quantity'],
                    'created_by_id' => $staff->id,
                ]);
            }

            return response()->json([
                'message' => 'Items added successfully',
                'success' => true,
                'status' => 'success',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function editItems(Request $request, $id)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|numeric|not_in:NaN,INF,-INF',
            'items.*.product_id' => 'required|integer',
        ], [
            'items.required' => 'Please provide at least one treatment item.',
            'items.array' => 'The treatment items should be an array.',
            'items.min' => 'At least one item must be specified.',
            'items.*.quantity.required' => 'Each treatment item must have a quantity.',
            'items.*.quantity.numeric' => 'Quantity must be a valid number.',
            'items.*.quantity.not_in' => 'Quantity must be a real number and not invalid.',
            'items.*.product_id.required' => 'Each item must include a product reference.',
            'items.*.product_id.integer' => 'Product ID must be a valid detail.',
        ]);

        try {
            $staff = Auth::user();
            $treatment = Treatment::with(['notes', 'items'])->find($id);

            if (!$treatment) {
                return response()->json([
                    'message' => 'Treatment record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($treatment->status !== TreatmentStatus::IN_PROGRESS->value) {
                return response()->json([
                    'message' => 'Cannot edit the items because the treatment is not in-progress',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($treatment->items->isNotEmpty()) {
                $treatment->items()->delete();
            }

            foreach ($request['items'] as $currentItem) {
                $product = Product::find($currentItem['product_id']);

                if (!$product) {
                    return response()->json([
                        'message' => "Product with ID {$currentItem['product_id']} not found",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                TreatmentItem::create([
                    'treatment_id' => $treatment->id,
                    'product_id' => $product->id,
                    'quantity' => $currentItem['quantity'],
                    'created_by_id' => $staff->id,
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function addNote($id, Request $request)
    {
        $request->validate([
            'title' => 'nullable | string | max:255',
            'content' => 'required | string'
        ]);

        try {
            $staff = Auth::user();
            $treatment = Treatment::find($id);

            if (!$treatment) {
                return [
                    'message' => 'Treatment record not found',
                    'success' => false,
                    'status' => 'error',
                ];
            }

            if ($treatment->status !== TreatmentStatus::IN_PROGRESS->value) {
                return response()->json(
                    [
                        'message' => 'Cannot add a new note because the treatment has been closed',
                        'success' => false,
                        'status' => 'error',
                    ],
                    400
                );
            }

            Note::create([
                'treatment_id' => $treatment->id,
                'content' => $request['content'],
                'created_by_id' => $staff->id,
            ]);

            return response()->json([
                'message' => 'Note added successfully',
                'success' => true,
                'status' => 'success',
            ], 200);
        } catch (Exception $e) {
            Log::error('Error in addNote: ' . $e->getMessage());
            return  response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }
}
