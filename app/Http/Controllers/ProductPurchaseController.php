<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ProductPurchaseItem;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductPurchaseController extends Controller
{
    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 15);
            $status = $request->input('status');
            $searchQuery = $request->input('q');

            $query = ProductPurchase::with([
                'addedBy',
                'approvedBy',
                'lastUpdatedBy',
                'purchasedItems.product.manufacturer'
            ])->when($status, function ($q) use ($status) {
                $q->where('status', $status);
            })->when($searchQuery, function ($q) use ($searchQuery) {
                $q->where('purchase_receipt', $searchQuery);
            });

            $purchaseRecords = $query->orderByDesc('created_at')->paginate($limit);

            return response()->json([
                'message' => 'SalesRecords fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $purchaseRecords
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function findOne($id)
    {
        try {
            $record = ProductPurchase::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'The record detail not found',
                    'status' => 'error'
                ], 400);
            }

            return response()->json([
                'message' => 'Record fetched Successfully',
                'status' => 'success',
                'success' => true,
                'data' => $record,
            ]);
        } catch (Exception $e) {
            Log::error($e);
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function delete($id)
    {
        try {
            $record = ProductPurchase::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'The record detail not found',
                    'status' => 'error'
                ], 400);
            }

            $record->delete();

            return response()->json([
                'message' => 'Record deleted Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error($e);
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function approve($id)
    {
        try {
            $record = ProductPurchase::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'The record detail not found',
                    'status' => 'error'
                ], 400);
            }

            $staff = Auth::user();
            $allowedRoles = ['ADMIN', 'CASHIER'];

            if (!in_array($staff->role, $allowedRoles)) {
                return response()->json([
                    'message' => "You don't have the permission to approve the record",
                    'status' => 'error'
                ], 400);
            }

            if ($record->status === 'APPROVED') {
                return response()->json([
                    'message' => 'The record has been approved already',
                    'status' => 'error'
                ], 400);
            }

            $record->approved_by_id = $staff->id;
            $record->status = 'APPROVED';
            $record->history = array_merge($record->history ?? [], [
                [
                    'title' => 'APPROVED',
                    'date' => now(),
                ],
            ]);

            $record->save();

            return response()->json([
                'message' => 'Record approved Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (BadRequestException $badRequestException) {
            return;
        } catch (Exception $e) {
            Log::error($e->getMessage());
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function disapprove($id)
    {
        try {
            $record = ProductPurchase::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'The record detail not found',
                    'status' => 'error'
                ], 400);
            }

            $staff = Auth::user();
            $allowedRoles = ['ADMIN', 'CASHIER'];

            if (!in_array($staff->role, $allowedRoles)) {
                return response()->json([
                    'message' => "You don't have the permission to disapprove the record",
                    'status' => 'error'
                ], 400);
            }

            if ($record->status === 'DISAPPROVED') {
                return response()->json([
                    'message' => 'The record has been disapproved already',
                    'status' => 'error'
                ], 400);
            }

            $record->approved_by_id = $staff->id;
            $record->status = 'DISAPPROVED';
            $record->history = array_merge($record->history ?? [], [
                [
                    'title' => 'DISAPPROVED',
                    'date' => now(),
                ],
            ]);

            $record->save();

            return response()->json([
                'message' => 'Record disapproved Successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error($e);
            throw new HttpException(500, 'Something went wrong. Try again in 5 minutes');
        }
    }

    public function create(Request $request)
    {
        $request->validate([
            'purchase_receipt' => ['required', 'string', 'max:255', 'unique:product_purchases,purchase_receipt'],
            'purchase_date' => ['nullable', 'date'],
            'purchased_items' => ['required', 'array', 'min:1'],
            'purchased_items.*.product_id' => ['required', 'exists:products,id'],
            'purchased_items.*.purchase_price' => ['required', 'numeric'],
            'purchased_items.*.number_of_cartons' => ['required', 'integer'],
            'purchased_items.*.number_of_packs' => ['required', 'integer'],
            'purchased_items.*.total_unit_quantity' => ['required', 'integer'],
        ]);

        try {
            $staff = Auth::user();

            $existingRecord = ProductPurchase::where('purchase_receipt', $request->purchase_receipt)->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Record with the same receipt detail already exists',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            if ($this->hasDuplicatedProducts($request->purchased_items)) {
                return response()->json([
                    'message' => 'There are duplicated records for a particular product. Please check and try again.',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $purchasedItems = [];
            $totalAmount = 0;

            foreach ($request->purchased_items as $item) {
                $product = Product::with('manufacturer')->find($item['product_id']);

                if (!$product) {
                    return response()->json([
                        'message' => 'Product not found: ' . $item['product_id'],
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                $inventoryItem = new ProductPurchaseItem();
                $inventoryItem->product_id = $product->id;
                $inventoryItem->manufacturer_id = $product->manufacturer->id ?? null;
                $inventoryItem->number_of_cartons = $item['number_of_cartons'];
                $inventoryItem->number_of_packs = $item['number_of_packs'];
                $inventoryItem->purchase_price = number_format((float) $item['purchase_price'], 3, '.', '');
                $inventoryItem->total_quantity = $item['total_unit_quantity'];
                $inventoryItem->save();
                $totalAmount += (float) $inventoryItem->purchase_price;
                $purchasedItems[] = $inventoryItem->id;
            }

            $inventory = new ProductPurchase();
            $inventory->purchase_receipt = $request->purchase_receipt;
            $inventory->purchase_date = $request->purchase_date;
            $inventory->added_by_id = $staff->id;
            $inventory->last_updated_by_id = $staff->id;
            $inventory->total_amount = number_format($totalAmount, 3, '.', '');
            $inventory->status = 'CREATED';
            $inventory->history = json_encode([
                ['title' => 'CREATED', 'date' => now()],
            ]);
            $inventory->save();

            ProductPurchaseItem::whereIn('id', $purchasedItems)->update(['purchase_id' => $inventory->id]);

            return response()->json([
                'message' => 'Inventory created successfully',
                'success' => true,
                'status' => 'success',
            ], 201);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    private function hasDuplicatedProducts(array $purchasedItems): bool
    {
        $productIds = [];

        foreach ($purchasedItems as $item) {
            if (in_array($item['product_id'], $productIds)) {
                return true;
            }
            $productIds[] = $item['product_id'];
        }

        return false;
    }
}
