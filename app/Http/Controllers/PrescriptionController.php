<?php

namespace App\Http\Controllers;

use App\Enums\PrescriptionItemStatus;
use App\Enums\PrescriptionStatus;
use App\Models\AnteNatal;
use App\Models\Note;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\ProductSalesItem;
use App\Models\Treatment;
use App\Models\Visitation;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class PrescriptionController extends Controller
{
    protected ProductSalesController $productSalesController;

    public function __construct(ProductSalesController $productSalesController)
    {
        $this->productSalesController = $productSalesController;
    }

    public function create(Request $request)
    {
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'treatment_id' => 'nullable|exists:treatments,id',
            'visitation_id' => 'nullable|exists:visitations,id',
            'ante_natal_id' => 'nullable|exists:ante_natals,id',
            'prescription_items' => 'required|array',
            'prescription_items.*.product_id' => 'required|exists:products,id',
            'prescription_items.*.dosage' => 'required|numeric|min:0',
            'prescription_items.*.frequency' => 'required|numeric|min:0',
            'prescription_items.*.duration' => 'required|numeric|min:0',
            'prescription_items.*.instructions' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        DB::beginTransaction();

        try {
            $staff = Auth::user();

            $patient = Patient::find($validatedData['patient_id']);
            if (!$patient) {
                return response()->json([
                    'status' => 'error',
                    'success' => false,
                    'message' => "Patient detail not found."
                ], 400);
            }

            $treatment = $validatedData['treatment_id'] ?? null ? Treatment::find($validatedData['treatment_id']) : null;
            $visitation = $validatedData['visitation_id'] ?? null ? Visitation::find($validatedData['visitation_id']) : null;
            $anteNatal = $validatedData['ante_natal_id'] ?? null ? AnteNatal::find($validatedData['ante_natal_id']) : null;

            if (!empty($visitation)) {
                if ($visitation->status === "CONSULTED") {
                    return response()->json([
                        'message' => 'You cannot add prescription because the visitation has been marked as CONSULTED',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                if ($visitation->status !== "ACCEPTED") {
                    return response()->json([
                        'message' => 'You need to accept the visitation before adding prescription',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            if (!empty($anteNatal)) {
                if ($anteNatal->status === "DELIVERED") {
                    return response()->json([
                        'message' => 'You cannot add prescription, because the ante-natal has been marked as completed',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }
            }

            // $prescription = new Prescription();
            // $prescription->patient_id = $patient->id;
            // $prescription->treatment_id = optional($treatment)->id;
            // $prescription->visitation_id = optional($visitation)->id;
            // $prescription->ante_natal_id = optional($anteNatal)->id;
            // $prescription->requested_by_id = $staff->id;
            $prescription = Prescription::create([
                'patient_id' => $patient->id,
                'treatment_id' => optional($treatment)->id,
                'visitation_id' => optional($visitation)->id,
                'ante_natal_id' => optional($anteNatal)->id,
                'requested_by_id' => $staff->id,
            ]);

            if ($request->filled('notes')) {
                $note = new Note();
                $note->content = $validatedData['notes'];
                $note->created_by_id = $staff->id;
                $note->save();
                $prescription->notes()->save($note);
            }

            $prescriptionItems = [];

            foreach ($validatedData['prescription_items'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    Log::info("Prescription Error: Product $item->product_id not found");
                    return response()->json([
                        'message' => "One of product(s) cannot does not exist in the record.",
                        'success' => false,
                        'status' => 'error',
                    ], 404);
                }

                $dosage = (float) $item['dosage'];
                $frequency = (float) $item['frequency'];
                $duration = (float) $item['duration'];
                $unitPrice = (float) $product->unit_price;

                $quantityNeed = $duration * $frequency * $dosage;
                $amount = $duration * $frequency * $dosage * $unitPrice;

                if ($product->quantity_available_for_sales < $quantityNeed) {
                    return response()->json([
                        'message' => "Insufficient sales quantity for {$product->brand_name}.",
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $prescriptionItem = new PrescriptionItem();
                $prescriptionItem->prescription_id = $prescription->id;
                $prescriptionItem->product_id = $product->id;
                $prescriptionItem->dosage = $dosage;
                $prescriptionItem->frequency = $frequency;
                $prescriptionItem->duration = $duration;
                $prescriptionItem->instructions = $item['instructions'] ?? null;
                $prescriptionItem->save();

                $saleItem = new ProductSalesItem();
                $saleItem->amount = round($amount, 2);
                $saleItem->quantity_sold = $duration * $frequency * $dosage;
                $saleItem->product_id = $product->id;
                $saleItem->save();

                $prescriptionItems[] = $saleItem;
            }

            $salesRecord = $this->productSalesController->createPrescriptionSalesRecord(
                $prescriptionItems,
                $patient->fullname,
                $patient->id
            );

            $prescription->salesRecord()->save($salesRecord);
            $prescription->save();

            DB::commit();

            return response()->json([
                'message' => 'Prescription added successfully',
                'success' => true,
                'status' => 'success',
                'data' => $prescription
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'An error occurred while trying to create a prescription',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    // public function create(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'patient_id' => 'required|exists:patients,id',
    //         'treatment_id' => 'nullable|exists:treatments,id',
    //         'visitation_id' => 'nullable|exists:visitations,id',
    //         'ante_natal_id' => 'nullable|exists:ante_natals,id',
    //         'prescription_items' => 'required|array',
    //         'prescription_items.*.product_id' => 'required|exists:products,id',
    //         'prescription_items.*.dosage' => 'required|string',
    //         'prescription_items.*.frequency' => 'required|string',
    //         'prescription_items.*.instructions' => 'nullable|string',
    //         'prescription_items.*.duration' => 'required|string',
    //         'notes' => 'nullable|string'
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $staff = Auth::user();
    //         $patient = Patient::where('id', $validatedData['patient_id'])->first();
    //         if (!$patient) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'success' => false,
    //                 'message' => "Patient detail not found."
    //             ], 400);
    //         }

    //         $treatment = isset($validatedData['treatment_id'])
    //             ? Treatment::find($validatedData['treatment_id'])
    //             : null;

    //         $visitation = isset($validatedData['visitation_id'])
    //             ? Visitation::find($validatedData['visitation_id'])
    //             : null;

    //         $anteNatal = isset($validatedData['ante_natal_id'])
    //             ? AnteNatal::find($validatedData['ante_natal_id'])
    //             : null;

    //         $prescription = new Prescription();
    //         $prescription->patient_id = $patient->id;
    //         $prescription->treatment_id = optional($treatment)->id;
    //         $prescription->visitation_id = optional($visitation)->id;
    //         $prescription->ante_natal_id = optional($anteNatal)->id;
    //         $prescription->requested_by_id = $staff->id;

    //         if ($request->filled('notes')) {
    //             $note = new Note();
    //             $note->content = $validatedData['notes'];
    //             $note->created_by_id = $staff->id;
    //             $note->save();
    //             $prescription->notes[] = $note;
    //         }

    //         $prescription->save();

    //         $prescriptionItems = [];
    //         foreach ($validatedData['prescription_items'] as $item) {
    //             $product = Product::find($item['product_id']);

    //             if ($product) {
    //                 $prescriptionItem = new PrescriptionItem();
    //                 $prescriptionItem->prescription_id = $prescription->id;
    //                 $prescriptionItem->product_id = $product->id;
    //                 $prescriptionItem->dosage = $item['dosage'];
    //                 $prescriptionItem->frequency = $item['frequency'];
    //                 $prescriptionItem->instructions = $item['instructions'] ?? null;
    //                 $prescriptionItem->duration = $item['duration'];
    //                 $prescriptionItem->save();


    //                 $saleItem = new ProductSalesItem();
    //                 $saleItem->amount = bcmul($product->unit_price, 1, 2);
    //                 $saleItem->quantity_sold = 1;
    //                 $saleItem->product_id = $product->id;
    //                 $saleItem->save();
    //                 $prescriptionItems[] = $saleItem;
    //             }
    //         }

    //         $payment  = $this->productSalesController->createPrescriptionSalesRecord($prescriptionItems, $patient->fullname, $patient->id);

    //         $prescription->salesRecord->payment_id = $payment->id;
    //         $prescription->save();

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Prescription added successfully',
    //             'success' => true,
    //             'status' => 'success',
    //             'data' => $prescription
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();

    //         Log::info($e->getMessage());
    //         // Handle any other exception
    //         return response()->json([
    //             'message' => 'An error occurred while trying to create a prescription',
    //             'status' => 'error',
    //             'success' => false
    //         ], 500);
    //     }
    // }

    public function updatePrescriptionItemStatus($id, $status)
    {
        try {
            $staff = Auth::user();

            $prescriptionItem = PrescriptionItem::with(['prescription.requestedBy', 'product'])->find($id);
            if (!$prescriptionItem) {
                throw new BadRequestException('The item detail not found');
            }

            if ($staff->role !== 'ADMIN') {
                throw new BadRequestException('You cannot mark the item as dispense. Only a user with role of pharmacist has the authorization to do it');
            }

            if ($prescriptionItem->status === $status) {
                throw new BadRequestException("The selected item already marked as " . strtolower($status));
            }

            if (!$prescriptionItem->product && $status === 'DISPENSE') {
                throw new BadRequestException('You can only mark an available item in store as dispensed');
            }

            if ($prescriptionItem->status === 'NOT_AVAILABLE' && $status === 'DISPENSE') {
                throw new BadRequestException('You can only mark an available item as dispensed');
            }

            $prescriptionItem->status = $status;
            $prescriptionItem->save();

            return response()->json([
                'message' => 'Selected Item status updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestException $e) {
            Log::info($e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'status' => 'error', 'success' => false], 400);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function markItemsAsDispensed(Request $request, string $id)
    {
        $validated = $request->validate([
            'prescription_items' => 'required|array|min:1',
            'prescription_items.*' => 'required|numeric|exists:prescription_items,id',
        ], [
            'prescription_items.required' => 'At least one prescription item is required.',
            'prescription_items.array' => 'Prescription items must be an array.',
            'prescription_items.*.exists' => 'One or more prescription items do not exist.',
        ]);

        try {
            $staff = Auth::user();

            $prescription = Prescription::with(['requestedBy', 'salesRecord.payment'])->find($id);

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // if ($staff->role !== 'PHARMACIST') {
            //     return response()->json([
            //         'message' => 'Only a pharmacist can mark prescription items as dispensed.',
            //         'status' => 'error',
            //         'success' => false,
            //     ], 400);
            // }

            if (!$prescription->salesRecord->payment->id) {
                return response()->json([
                    'message' => 'No Payment attached to the prescription',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }


            if ($prescription->salesRecord->payment->status !== 'COMPLETED') {
                return response()->json([
                    'message' => 'You are not allowed to mark the prescription item as dispense. Payment has not been made.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $existingItems = PrescriptionItem::whereIn('id', $validated['prescription_items'])->get();

            if (count($existingItems) !== count($validated['prescription_items'])) {
                return response()->json([
                    'message' => 'Some items could not be found. Please remove irrelevant items from the list.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            Log::info($existingItems);

            foreach ($existingItems as $item) {
                $item->status = PrescriptionItemStatus::DISPENSE->value;
                $item->save();
            }

            $prescription->status = PrescriptionStatus::APPROVED;

            return response()->json([
                'message' => 'Items marked as dispensed',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Error dispensing prescription items: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again later.',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function markItemsNotAvailable(Request $request, string $id)
    {
        $validated = $request->validate([
            'prescription_items' => 'required|array|min:1',
            'prescription_items.*' => 'required|numeric|exists:prescription_items,id',
        ], [
            'prescription_items.required' => 'At least one prescription item is required.',
            'prescription_items.array' => 'Prescription items must be an array.',
            'prescription_items.*.exists' => 'One or more prescription items do not exist.',
        ]);

        try {
            $staff = Auth::user();

            $prescription = Prescription::with(['requestedBy', 'salesRecord.payment'])->find($id);
            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // if ($staff->role === 'PHARMACIST') {
            //     return response()->json([
            //         'message' => 'You are not allowed to mark the prescription item as unavailable. Only a pharmacist can do that.',
            //         'status' => 'error',
            //         'success' => false,
            //     ], 400);
            // }

            $existingItems = PrescriptionItem::whereIn('id', $validated['prescription_items'])->get();

            if (count($existingItems) !== count($validated['prescription_items'])) {
                return response()->json([
                    'message' => 'Some items could not be found. Please remove irrelevant items from the list.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // DB::beginTransaction();

            foreach ($existingItems as $item) {
                // Mark each item as "Not Available"
                $item->status = PrescriptionItemStatus::NOT_AVAILABLE->value;
                $item->save();
            }

            // DB::commit();

            return response()->json([
                'message' => 'Items marked as Not Available',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error marking items as Not Available: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again later.',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updatePrescriptionItem(Request $request, $id)
    {
        Log::info($id);
        $updatePrescriptionItemDto = $request->validate([
            'dosage' => 'required|numeric|min:0',
            'product_id' => 'required|exists:products,id',
            'frequency' => 'required|numeric|min:0',
            'instructions' => 'nullable|string|max:1000',
            'duration' => 'required|numeric|min:0',
        ], [
            'product_id.required' => 'The product ID field is required.',
            'product_id.exists' => 'The selected product does not exist.',
            'dosage.required' => 'The dosage field is required.',
            'dosage.numeric' => 'The dosage must be a number.',
            'dosage.min' => 'The dosage must be at least 0.',
            'frequency.required' => 'The frequency field is required.',
            'frequency.numeric' => 'The frequency must be a number.',
            'frequency.min' => 'The frequency must be at least 0.',
            'instructions.string' => 'The instructions must be a string.',
            'instructions.max' => 'The instructions must not exceed 1000 characters.',
            'duration.required' => 'The duration field is required.',
            'duration.numeric' => 'The duration must be a number.',
            'duration.min' => 'The duration must be at least 0.',
        ]);

        try {
            $staff = Auth::user();

            // Fetch the product to update
            $product = Product::find($updatePrescriptionItemDto['product_id']);
            if (!$product) {
                throw new BadRequestException('The product detail not found');
            }

            // Fetch the prescription item
            $prescriptionItem = PrescriptionItem::with(['prescription.requestedBy', 'prescription.salesRecord', 'prescription.salesRecord.payment'])->find($id);
            if (!$prescriptionItem) {
                throw new BadRequestException('The item detail not found');
            }

            // Ensure only the requesting staff can update the item
            if ($prescriptionItem->prescription->requested_by_id !== $staff->id) {
                throw new BadRequestException('You cannot update the item. Only the person that created it can update it');
            }

            // Check if the payment is completed
            $payment = $prescriptionItem->prescription->salesRecord->payment ?? null;

            if ($payment) {
                if ($payment->status === "COMPLETED") {
                    return response()->json([
                        'message' => 'Cannot update item. Payment for the prescription has been made',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }
            }

            // Update the prescription item
            $prescriptionItem->dosage = $updatePrescriptionItemDto['dosage'];
            $prescriptionItem->frequency = $updatePrescriptionItemDto['frequency'];
            $prescriptionItem->product_id = $product->id;
            $prescriptionItem->instructions = $updatePrescriptionItemDto['instructions'];
            $prescriptionItem->duration = $updatePrescriptionItemDto['duration'];
            $prescriptionItem->save();

            // Recalculate the total amount of the prescription
            $totalAmount = 0;
            foreach ($prescriptionItem->prescription->items as $item) {
                $totalAmount += ($item->duration * $item->frequency * $item->dosage * $item->product->unit_price);
            }

            // Update the prescription's payment with the new total amount
            $payment->amount = $totalAmount;
            $payment->amount_payable = round($totalAmount, 2);
            $payment->save();

            return response()->json([
                'message' => 'Prescription item detail updated successfully',
                'status' => 'success',
                'success' => true
            ]);
        } catch (BadRequestException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json(['message' => 'Something went wrong. Try again'], 500);
        }
    }

    // public function updatePrescriptionItem(Request $request, $id)
    // {
    //     Log::info($id);
    //     $updatePrescriptionItemDto = $request->validate([
    //         'dosage' => 'required|string| max:255',
    //         'product_id' => 'required|exists:products,id',
    //         'frequency' => 'required|string|max:255',
    //         'instructions' => 'nullable|string|max:1000',
    //         'duration' => 'required|string|max:255',
    //     ], [
    //         'product_id.required' => 'The product ID field is required.',
    //         'product_id.exists' => 'The selected product does not exist.',
    //         'dosage.required' => 'The dosage field is required.',
    //         'dosage.numeric' => 'The dosage must be a number.',
    //         'dosage.min' => 'The dosage must be at least 1.',
    //         'frequency.required' => 'The frequency field is required.',
    //         'frequency.string' => 'The frequency must be a string.',
    //         'frequency.max' => 'The frequency must not exceed 255 characters.',
    //         'instructions.string' => 'The instructions must be a string.',
    //         'instructions.max' => 'The instructions must not exceed 1000 characters.',
    //         'duration.required' => 'The duration field is required.',
    //         'duration.string' => 'The duration must be a string.',
    //         'duration.max' => 'The duration must not exceed 255 characters.',
    //     ]);

    //     try {
    //         $staff = Auth::user();

    //         $product = Product::find($updatePrescriptionItemDto['product_id']);
    //         if (!$product) {
    //             throw new BadRequestException('The product detail not found');
    //         }

    //         $prescriptionItem = PrescriptionItem::with(['prescription.requestedBy', 'prescription.payment'])->find($id);
    //         if (!$prescriptionItem) {
    //             throw new BadRequestException('The item detail not found');
    //         }

    //         if ($prescriptionItem->prescription->requested_by_id !== $staff->id) {
    //             throw new BadRequestException('You cannot update the item. Only the person that created it can update it');
    //         }

    //         $payment = $prescriptionItem->prescription->payment ?? null;

    //         if (!$payment) {
    //             return response()->json([
    //                 'message' => 'No payment is attached to the prescription',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         if ($payment && $payment->status === "COMPLETED") {
    //             return response()->json([
    //                 'message' => 'Cannot update item. Payment for the prescription has been made',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $prescriptionItem->dosage = $updatePrescriptionItemDto['dosage'];
    //         $prescriptionItem->frequency = $updatePrescriptionItemDto['frequency'];
    //         $prescriptionItem->product_id = $product->id;
    //         $prescriptionItem->instructions = $updatePrescriptionItemDto['instructions'];
    //         $prescriptionItem->duration = $updatePrescriptionItemDto['duration'];
    //         $prescriptionItem->save();

    //         return response()->json([
    //             'message' => 'Prescription item detail updated successfully',
    //             'status' => 'success',
    //             'success' => true
    //         ]);
    //     } catch (BadRequestException $e) {
    //         return response()->json(['message' => $e->getMessage()], 400);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());

    //         return response()->json(['message' => 'Something went wrong. Try again'], 500);
    //     }
    // }

    public function removeItems(Request $request, $id)
    {
        $validatedData = $request->validate([
            'prescription_items' => 'required|array',
            'prescription_items.*' => 'required|exists:prescription_items,id',
        ]);

        DB::beginTransaction();

        try {
            $staff = Auth::user();

            $prescription = Prescription::with(['requestedBy', 'salesRecord', 'salesRecord.payment'])
                ->where('id', $id)
                ->first();

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 404);
            }


            if (!$prescription->salesRecord->payment) {
                return response()->json([
                    'message' => 'No payment is attached',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($prescription->salesRecord->payment->status === "COMPLETED") {
                return response()->json([
                    'message' => 'Cannot remove item(s). Payment for the prescription has been made',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $currentUserId = $staff->id;
            if ($currentUserId !== $prescription->requested_by_id) {
                return response()->json([
                    'message' => 'You are not allowed to delete the prescription items',
                    'status' => 'error',
                    'success' => false,
                ], 403);
            }

            if ($prescription->status === PrescriptionStatus::APPROVED) {
                return response()->json([
                    'message' => 'Cannot remove items because the prescription has been marked as verified',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Initialize arrays for validation
            $existingItems = [];
            $nonExistentItems = [];

            // Check if all prescription items exist
            foreach ($validatedData['prescription_items'] as $itemId) {
                $item = PrescriptionItem::find($itemId);

                if ($item) {
                    $existingItems[] = $item;
                } else {
                    $nonExistentItems[] = $itemId;
                }
            }

            // Return an error if there are invalid items
            if (!empty($nonExistentItems)) {
                DB::rollBack();

                return response()->json([
                    'message' => 'Some items cannot be found. Remove irrelevant items from the list.',
                    'invalid_items' => $nonExistentItems,
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Soft-delete existing items
            foreach ($existingItems as $item) {
                $item->delete();
            }

            // Commit transaction
            DB::commit();

            return response()->json([
                'message' => 'Items removed from the prescription successfully.',
                'status' => 'success',
                'success' => true,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();

            Log::info($e->getMessage());

            return response()->json([
                'message' => 'An error occurred while removing items.',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function addMoreItems($id, Request $request)
    {
        $validated = $request->validate([
            'prescription_items' => 'required|array',
            'prescription_items.*.product_id' => 'required|exists:products,id',
            'prescription_items.*.dosage' => 'required|numeric|min:0',
            'prescription_items.*.frequency' => 'required|numeric|min:0',
            'prescription_items.*.duration' => 'required|numeric|min:0',
            'prescription_items.*.instructions' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $prescription = Prescription::with('payment')->find($id);

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $payment = $prescription->salesRecord->payment;

            if (!$payment) {
                return response()->json([
                    'message' => 'No payment is attached to the prescription',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($payment->status === 'COMPLETED') {
                return response()->json([
                    'message' => 'Cannot add item(s). Payment for the prescription has been made',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($prescription->status === 'DISPENSE') {
                return response()->json([
                    'message' => 'Cannot add item(s). Prescribed items have been dispensed',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $totalNewAmount = 0;

            foreach ($validated['prescription_items'] as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    continue; // Shouldn’t occur due to validation
                }

                $dosage = (float) $item['dosage'];
                $frequency = (float) $item['frequency'];
                $duration = (float) $item['duration'];
                $unitPrice = (float) $product->price;

                $subtotal = $duration * $frequency * $dosage * $unitPrice;
                $totalNewAmount += $subtotal;

                $prescription->items()->create([
                    'product_id' => $product->id,
                    'dosage' => $dosage,
                    'frequency' => $frequency,
                    'duration' => $duration,
                    'instructions' => $item['instructions'] ?? null,
                ]);
            }

            $payment->amount += $totalNewAmount;
            $payment->save();

            DB::commit();

            return response()->json([
                'message' => 'Items added successfully and payment updated',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Add More Items Error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function addMoreItems($id, Request $request)
    // {
    //     $validated = $request->validate([
    //         'prescription_items' => 'required|array',
    //         'prescription_items.*.product_id' => 'required|exists:products,id',
    //         'prescription_items.*.dosage' => 'required|string',
    //         'prescription_items.*.frequency' => 'required|string',
    //         'prescription_items.*.instructions' => 'nullable|string',
    //         'prescription_items.*.duration' => 'required|string',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $prescription = Prescription::with('payment')->find($id);

    //         if (!$prescription) {
    //             return response()->json([
    //                 'message' => 'Prescription detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $payment = $prescription->salesRecord->payment ?? null;
    //         Log::info($prescription->salesRecord->payment);


    //         if (!$payment) {
    //             return response()->json([
    //                 'message' => 'No payment is attached to the prescription',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         if ($payment && $payment->status === "COMPLETED") {
    //             return response()->json([
    //                 'message' => 'Cannot add item(s). Payment for the prescription has been made',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         if ($prescription->status === 'DISPENSE') {
    //             return response()->json([
    //                 'message' => 'Cannot add item(s). Prescribed items has been dispensed',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $totalNewAmount = 0;
    //         $prescriptionItemsData = $validated['prescription_items'];

    //         foreach ($prescriptionItemsData as $currentItem) {
    //             $product = Product::find($currentItem['product_id']);

    //             if ($product) {
    //                 $prescriptionItem = new PrescriptionItem();
    //                 $prescriptionItem->dosage = $currentItem['dosage'];
    //                 $prescriptionItem->frequency = $currentItem['frequency'];
    //                 $prescriptionItem->instructions = $currentItem['instructions'];
    //                 $prescriptionItem->duration = $currentItem['duration'];
    //                 $prescriptionItem->product_id = $product->id;
    //                 $prescriptionItem->prescription()->associate($prescription);
    //                 $prescriptionItem->save();

    //                 $totalNewAmount += $product->price ?? 0;
    //             }
    //         }

    //         // Update payment amount if it exists
    //         if ($payment) {
    //             $payment->amount += $totalNewAmount;
    //             $payment->save();
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Items added successfully and payment updated',
    //             'status' => 'success',
    //             'success' => true,
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Log::info($e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    // public function addMoreItems($id, Request $request)
    // {
    //     $validated = $request->validate([
    //         'prescription_items' => 'required|array',
    //         'prescription_items.*.product_id' => 'required|exists:products,id',
    //         'prescription_items.*.dosage' => 'required|string',
    //         'prescription_items.*.frequency' => 'required|string',
    //         'prescription_items.*.instructions' => 'nullable|string',
    //         'prescription_items.*.duration' => 'required|string',
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $prescription = Prescription::find($id);
    //         if (!$prescription) {
    //             return response()->json([
    //                 'message' => 'Prescription detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         $prescriptionItemsData = $validated['prescription_items'];

    //         foreach ($prescriptionItemsData as $currentItem) {
    //             $product = Product::find($currentItem['product_id']);

    //             if ($product) {
    //                 $prescriptionItem = new PrescriptionItem();
    //                 $prescriptionItem->dosage = $currentItem['dosage'];
    //                 $prescriptionItem->frequency = $currentItem['frequency'];
    //                 $prescriptionItem->instructions = $currentItem['instructions'];
    //                 $prescriptionItem->duration = $currentItem['duration'];
    //                 $prescriptionItem->product_id = $product->id;
    //                 $prescriptionItem->prescription()->associate($prescription);
    //                 $prescriptionItem->save();
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Items added successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();

    //         Log::info($e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function findAll(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 10);
            $status = strtoupper($request->input('status', ''));
            $queryText = $request->input('q', '');

            // $query = Prescription::with(['patient', 'requestedBy', 'items', 'notes', 'salesRecord.payment']);
            $query = Prescription::with([
                'patient',
                'requestedBy',
                'items',
                'notes',
                'salesRecord.payment' => function ($query) {
                    $query->select('id', 'status', 'payable_id', 'payable_type');
                }
            ]);


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

            $prescription = Prescription::with('requestedBy')->find($id);

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription not find',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            Log::info($prescription->requested_by_id . $staffId);

            if ($prescription->requested_by_id != $staffId) {
                return response()->json([
                    'message' => 'You do not have the authorized permission to delete the prescription',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            $prescription->delete();

            return response()->json([
                'message' => 'Prescription deleted successfully',
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
            $prescription = Prescription::with(['requestedBy.assignedBranch', 'notes.createdBy', 'items.product', 'treatment', 'patient', 'salesRecord.payment'])->find($id);

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription not found',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            return response()->json([
                'message' => 'Prescription deleted successfully',
                'status' => 'success',
                "success" => true,
                'data' => $prescription
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

    public function addNote(Request $request, $id)
    {
        $request->validate([
            'content' => 'required | string',
            'title' => 'nullable | string'
        ]);

        try {
            $staffId = Auth::id();
            $prescription = Prescription::with(['requestedBy.assignedBranch', 'notes.createdBy', 'items.product', 'treatment', 'patient'])->find($id);

            if (!$prescription) {
                return response()->json([
                    'message' => 'Prescription not found',
                    'status' => 'error',
                    "success" => false
                ], 400);
            }

            $note = new Note();
            $note->content = $request['content'];
            $note->title = $request['title'];
            $note->prescription_id = $prescription->id;
            $note->created_by_id = $staffId;
            $note->save();

            return response()->json([
                'message' => 'Note added successfully',
                'status' => 'success',
                "success" => true,
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
}
