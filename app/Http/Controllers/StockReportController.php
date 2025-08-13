<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockReport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockReportController extends Controller
{
    public function findAll(Request $request)
    {
        try {
            $type = $request->get('type', '');
            $searchQuery = $request->get('q', '');
            $destination = $request->get('destination', '');
            $limit = $request->get('limit', 50);

            $query = StockReport::with(['addedBy:id,firstname,lastname', 'lastUpdatedBy:id,firstname,lastname', 'product:id,brand_name,generic_name,dosage_type'])->when($searchQuery, function ($query, $q) {
                $query->whereHas('product', function ($productQuery) use ($q) {
                    $productQuery->where(function ($qb) use ($q) {
                        $qb->where('brand_name', 'LIKE', "%{$q}%")
                            ->orWhere('generic_name', 'LIKE', "%{$q}%");
                    });
                });
            });;

            if (!empty($type)) {
                $query->where('transaction_type', $type);
            }

            if (!empty($destination)) {
                $query->where('destination', $destination);
            }

            $data = $query->orderBy('updated_at', 'desc')->paginate($limit);

            return response()->json([
                'message' => 'Stock reports fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $th) {
            Log::info($th->getMessage());
            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function findOne($id)
    {
        try {
            $report = StockReport::find($id);

            if (!$report) {
                return response()->json([
                    'message' => 'The stock report detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            return response()->json([
                'message' => 'Stock report detail fetched successfully',
                'success' => true,
                'status' => 'success',
                'data' => $report
            ], 200);
        } catch (Exception $e) {
            Log::error('Error updating service: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $report = StockReport::find($id);

            if (!$report) {
                return response()->json([
                    'message' => 'The stock report detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $report->delete();

            return response()->json([
                'message' => 'Stock report detail deleted successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error('Error updating service: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'product' => 'required | integer',
            'destination' => 'required | in:STORE,RACK',
            'quantity' => 'required | integer | min:1',
            'remark' => 'nullable | string | max:255'
        ]);

        DB::beginTransaction();

        try {
            $staffId = Auth::id();
            $product = Product::find($request['product']);

            if (!$product) {
                return response()->json([
                    'message' => 'Product detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Capture quantity before update
            $quantityBefore = null;
            $quantityAfter = null;
            $quantitySold = $product->quantity_sold;
            $quantityPurchased = null;

            // Adjust stock based on destination
            if ($request->destination === 'STORE') {
                $quantityBefore = $product->quantity_in_stock;

                $product->quantity_purchase += $request->quantity;
                $product->quantity_in_stock += $request->quantity;

                $quantityAfter = $product->quantity_in_stock;
                $quantityPurchased = $request->quantity;
            }

            if ($request->destination === 'RACK') {
                if ($product->quantity_in_stock < $request->quantity) {
                    return response()->json([
                        'message' => 'The number of items in the store is less than the quantity you want to move to rack',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $quantityBefore = $product->quantity_available_for_sales;

                $product->quantity_in_stock -= $request->quantity;
                $product->quantity_available_for_sales += $request->quantity;

                $quantityAfter = $product->quantity_available_for_sales;
            }

            // Create stock report
            $stockTransaction = new StockReport();
            $stockTransaction->product_id = $product->id;
            $stockTransaction->destination = $request->destination;
            $stockTransaction->added_by_id = $staffId;
            $stockTransaction->last_updated_by_id = $staffId;
            $stockTransaction->quantity = $request->quantity;
            $stockTransaction->transaction_type = 'RESTOCK';
            $stockTransaction->remarks = $request->remark;

            // Populate auditing fields
            $stockTransaction->unit_price = $product->unit_price ?? null;
            $stockTransaction->sales_price = $product->sales_price ?? null;
            $stockTransaction->quantity_before = $quantityBefore;
            $stockTransaction->quantity_after = $quantityAfter;
            $stockTransaction->quantity_purchased = $quantityPurchased;
            $stockTransaction->quantity_sold = $quantitySold;
            $stockTransaction->save();
            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Stock report detail created successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $th) {
            DB::rollBack();

            Log::error('Error creating stock report: ' . $th->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'product' => 'required|integer|exists:products,id',
            'destination' => 'required|in:STORE,RACK',
            'quantity' => 'required|integer|min:1',
            'remarks' => 'nullable|string|max:255'
        ]);

        try {
            $report = StockReport::with('product')->find($id);

            if (!$report) {
                return response()->json([
                    'message' => 'Report detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            DB::beginTransaction();

            $product = $report->product;
            $oldQuantity = $report->quantity;
            $newQuantity = (int) $request->quantity;

            // STEP 1: Revert old changes based on previous destination
            if ($report->destination === 'STORE') {
                $product->quantity_purchase -= $oldQuantity;
                $product->quantity_in_stock -= $oldQuantity;
            } elseif ($report->destination === 'RACK') {
                $product->quantity_in_stock += $oldQuantity;
                $product->quantity_available_for_sales -= $oldQuantity;
            }

            // STEP 2: Validate if enough stock is available for RACK movement
            if ($request->destination === 'RACK' && $product->quantity_in_stock < $newQuantity) {
                return response()->json([
                    'message' => 'The items in the store are less than the quantity you want to move to rack',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // STEP 3: Save old quantity_after as new quantity_before
            $newQuantityBefore = $report->quantity_after ?? 0;

            // STEP 4: Apply new quantity changes
            if ($request->destination === 'STORE') {
                $product->quantity_purchase += $newQuantity;
                $product->quantity_in_stock += $newQuantity;
            } elseif ($request->destination === 'RACK') {
                $product->quantity_in_stock -= $newQuantity;
                $product->quantity_available_for_sales += $newQuantity;
            }

            $newQuantityAfter = $product->quantity_in_stock;

            // STEP 5: Update report
            $report->destination = $request->destination;
            $report->product_id = $product->id;
            $report->quantity = $newQuantity;
            $report->quantity_before = $newQuantityBefore;
            $report->quantity_after = $newQuantityAfter;
            $report->quantity_purchased = $request->destination === 'STORE' ? $newQuantity : null;
            $report->quantity_sold = $request->destination === 'RACK' ? $newQuantity : null;
            $report->remarks = $request->remarks;
            $report->last_updated_by_id = Auth::id();
            $report->transaction_type = $report->transaction_type === 'RESTOCK'
                ? 'UPDATE_RESTOCK'
                : 'UPDATE_STOCK';

            // STEP 6: Update product status
            $product->status = $product->quantity_available_for_sales > 0 ? 'AVAILABLE' : 'OUT_OF_STOCK';

            $product->save();
            $report->save();

            DB::commit();

            return response()->json([
                'message' => 'Stock report updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Stock update error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }


    // public function update(Request $request, int $id)
    // {
    //     $request->validate([
    //         'product' => 'required|integer|exists:products,id',
    //         'destination' => 'required|in:STORE,RACK',
    //         'quantity' => 'required|integer|min:1',
    //         'remarks' => 'nullable|string|max:255'
    //     ]);

    //     try {
    //         $report = StockReport::with('product')->find($id);

    //         if (!$report) {
    //             return response()->json([
    //                 'message' => 'Report detail not found',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         Log::info($report);

    //         DB::beginTransaction();

    //         $product = $report->product;
    //         $oldQuantity = $report->quantity;
    //         $newQuantity = (int) $request->quantity;

    //         // STEP 1: Revert old stock impact
    //         if ($report->destination === 'STORE') {
    //             $product->quantity_purchase -= $oldQuantity;
    //             $product->quantity_in_stock -= $oldQuantity;
    //         }

    //         if ($report->destination === 'RACK') {
    //             $product->quantity_in_stock += $oldQuantity;
    //             $product->quantity_available_for_sales -= $oldQuantity;
    //         }

    //         // STEP 2: Validate RACK quantity constraint
    //         if ($request->destination === 'RACK' && $product->quantity_in_stock < $newQuantity) {
    //             return response()->json([
    //                 'message' => 'The items in the store are less than the quantity you want to move to rack',
    //                 'status' => 'error',
    //                 'success' => false,
    //             ], 400);
    //         }

    //         // STEP 3: Capture quantity_before (before applying new update)
    //         $quantityBefore = $product->quantity_in_stock;

    //         // STEP 4: Apply new stock updates
    //         if ($request->destination === 'STORE') {
    //             $product->quantity_purchase += $newQuantity;
    //             $product->quantity_in_stock += $newQuantity;
    //         }

    //         if ($request->destination === 'RACK') {
    //             $product->quantity_in_stock -= $newQuantity;
    //             $product->quantity_available_for_sales += $newQuantity;
    //         }

    //         // STEP 5: Capture quantity_after (after applying new update)
    //         $quantityAfter = $product->quantity_in_stock;

    //         // STEP 6: Update stock report
    //         $report->destination = $request->destination;
    //         $report->product_id = $product->id;
    //         $report->quantity_before = $quantityBefore;
    //         $report->quantity_after = $quantityAfter;
    //         $report->quantity = $newQuantity;
    //         $report->quantity_purchased = $request->destination === 'STORE' ? $newQuantity : null;
    //         $report->remarks = $request->remarks;
    //         $report->last_updated_by_id = Auth::id();
    //         $report->transaction_type = $report->transaction_type === 'RESTOCK'
    //             ? 'UPDATE_RESTOCK'
    //             : 'UPDATE_STOCK';

    //         $product->status = $product->quantity_available_for_sales > 0 ? 'AVAILABLE' : 'OUT_OF_STOCK';

    //         $product->save();
    //         $report->save();

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Stock report updated successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ], 200);
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         Log::error('Stock update error', ['error' => $e->getMessage()]);

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }
}
