<?php

namespace App\Http\Controllers;

use App\Imports\ProductImport;
use App\Models\Product;
use App\Models\ProductManufacturer;
use App\Models\StockReport;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductController extends Controller
{
    public function __construct(
        protected StockReportController $stockReportService,
    ) {}

    public function create(Request $request)
    {
        $request->validate([
            'brand_name' => ['required', 'string'],
            'expiry_date' => ['required', 'date'],
            'manufacturing_date' => ['required', 'date'],
            'dosage_strength' => ['required', 'string'],
            'unit_price' => ['required', 'numeric', 'max:999999.999'],
            'dosage_type' => ['required', 'string'],
            'stock_alert_threshold' => ['required', 'numeric', 'min:0'],
            // Optional fields
            'generic_name' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'manufacturer' => ['nullable', 'numeric', 'exists:product_manufacturers,id'],
            'storage_condition' => ['nullable', 'string', 'max:255'],
            'dimension' => ['nullable', 'string'],
            'nafdac_code' => ['nullable', 'string'],
            'weight' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'max:999999.999'],
            'batch_code' => ['nullable', 'string'],
        ]);

        try {
            $staff = Auth::user();

            $manufacturer = $request->filled('manufacturer')
                ? ProductManufacturer::find($request['manufacturer'])
                : null;

            if ($manufacturer && !$manufacturer) {
                return response()->json([
                    'message' => 'Manufacturer detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // if ($type && !$type) {
            //     return response()->json([
            //         'message' => 'Type detail not found',
            //         'success' => false,
            //         'status' => 'error',
            //     ], 400);
            // }

            // $existingProduct = Product::where(function ($query) use ($request, $manufacturer) {
            //     $query->where(function ($q) use ($request, $manufacturer) {
            //         $q->where('brand_name', strtolower(trim($request['brand_name'])))
            //             ->when($request['generic_name'], fn($q2) =>
            //             $q2->where('generic_name', strtolower(trim($request['generic_name']))))
            //             ->when($manufacturer, fn($q2) =>
            //             $q2->whereHas(
            //                 'manufacturer',
            //                 fn($m) =>
            //                 $m->where('name', $manufacturer->name)
            //             ));
            //     })
            //         ->orWhere('nafdac_code', $request['nafdac_code'])
            //         ->orWhere('batch_code', $request['batch_code']);
            // })->first();
            $existingProduct = Product::where(function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('brand_name', strtolower(trim($request['brand_name'])))
                        ->when(!empty($request['generic_name']), fn($q2) =>
                        $q2->where('generic_name', strtolower(trim($request['generic_name']))));

                    if (!empty($request['manufacturer']['name'])) {
                        $q->whereHas(
                            'manufacturer',
                            fn($m) =>
                            $m->where('name', strtolower(trim($request['manufacturer']['name'])))
                        );
                    }
                });

                if (!empty($request['nafdac_code'])) {
                    $query->orWhere('nafdac_code', $request['nafdac_code']);
                }

                if (!empty($request['batch_code'])) {
                    $query->orWhere('batch_code', $request['batch_code']);
                }
            })->first();
            Log::info($request->all());
            Log::info($existingProduct);


            if ($existingProduct) {
                if ($existingProduct->nafdac_code === $request['nafdac_code']) {
                    return response()->json([
                        'message' => 'A Product with the same nafdac code already exists',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                if ($existingProduct->batch_code === $request['batch_code']) {
                    return response()->json([
                        'message' => 'A Product with the same batch code already exists',
                        'success' => false,
                        'status' => 'error',
                    ], 400);
                }

                return response()->json([
                    'message' => 'A Product with the same brand and generic name from the same manufacturer already exists',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $product = new Product();
            $product->brand_name = strtolower(trim($request['brand_name']));
            $product->generic_name = strtolower(trim($request['generic_name'] ?? ''));
            $product->batch_code = trim($request['batch_code'] ?? '');
            $product->purchase_price = (string)($request['purchase_price'] ?? 0);
            $product->unit_price = (string)$request['unit_price'];
            $product->stock_alert_threshold = $request['stock_alert_threshold'];
            $product->sales_price = (string)$request['unit_price'];
            $product->description = strtolower(trim($request['description'] ?? ''));
            $product->dimension = strtolower($request['dimension'] ?? '');
            $product->expiry_date = $request['expiry_date'];
            $product->dosage_type = $request['dosage_type'];
            $product->dosage_strength = $request['dosage_strength'];
            $product->nafdac_code = $request['nafdac_code'] ?? '';
            $product->weight = $request['weight'] ?? null;
            $product->storage_condition = $request['storage_condition'] ?? null;
            $product->tracking_code = strtoupper(Str::random(12));
            $product->manufacturing_date = $request['manufacturing_date'];
            $product->added_by_id = $staff->id;
            $product->manufacturer_id = $manufacturer?->id;
            // $product->product_type_id = $type?->id;
            $product->save();

            return response()->json([
                'message' => 'Product created successfully',
                'status' => 'success',
                'success' => true,
            ], 201);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function searchProductByName(Request $request)
    {
        try {
            $queryBuilder = Product::query();

            // Get search query
            $query = $request->get('q', '');

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('brand_name', 'like', "%$query%")
                        ->orWhere('generic_name', 'like', "%$query%");
                });
            }

            // Always take 30 records
            $products = $queryBuilder->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Products records retrieved successfully',
                'data' => $products
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving product records',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function searchProducts(Request $request)
    {
        try {
            $queryBuilder = Product::query();

            // Get search query
            $query = $request->get('q', '');

            // Apply search filter if provided
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('brand_name', 'like', "%$query%")
                        ->orWhere('generic_name', 'like', "%$query%");
                });
            }

            $queryBuilder->where('is_available', true);

            // Always take 30 records
            $products = $queryBuilder->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Products records retrieved successfully',
                'data' => $products
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving product records',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function pointOfSalesSearch(Request $request)
    {
        try {
            $queryBuilder = Product::query();

            $query = $request->get('q', '');
            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('brand_name', 'like', "%$query%")
                        ->orWhere('generic_name', 'like', "%$query%");
                });
            }

            // Use paginate (e.g., 30 per page)
            $products = $queryBuilder->paginate(30);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Products records retrieved successfully',
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'An error occurred while retrieving product records',
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }



    public function searchProduct(Request $request)
    {
        try {
            $queryBuilder = Product::query();
            $query = $request->get('q', '');

            if (!empty($query)) {
                $queryBuilder->where(function ($qb) use ($query) {
                    $qb->where('brand_name', 'like', "%$query%")
                        ->orWhere('generic_name', 'like', "%$query%");
                });
            }

            $products = $queryBuilder->paginate(20);

            return response()->json([
                'success' => true,
                'status'  => 'success',
                'message' => 'Products records retrieved successfully',
                'data'    => $products
            ], 200);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'An error occurred while retrieving product records',
            ], 500);
        }
    }

    public function getReport()
    {
        try {
            $salesReport = app(ProductSalesController::class)->generateReport();
            $totalProducts = Product::count();
            $lastFiveDamagedProducts = Product::where('status', 'DAMAGED')
                ->orderBy('created_at', 'DESC')
                ->take(5)
                ->get();
            $totalDamageProducts = Product::where('status', 'DAMAGED')->count();
            $lastFiveOutOfStockProducts = Product::where('status', 'OUT-OF-STOCK')->take(5)->orderBy("created_at", 'DESC')->get();
            $totalOutOfStockProducts = Product::where('status', 'OUT-OF-STOCK')->count();
            $lastFiveExpiredProducts = Product::where('status', 'EXPIRED')->take(5)->orderBy("created_at", 'DESC')->get();
            $totalExpiredProducts = Product::where('status', 'EXPIRED')->count();

            return response()->json([
                'message' => 'Laboratory report detail fetched successfully',
                'success' => true,
                'status' => 'success',
                'data' => [
                    'sales_chart_data' => $salesReport['chart_data'],
                    'total_damage_products' => $totalDamageProducts,
                    'total_out_of_stock_products' => $totalOutOfStockProducts,
                    'total_expired_products' => $totalExpiredProducts,
                    'total_product_categories' => 0,
                    'total_products' => $totalProducts,
                    'total_sales_amount' => $salesReport['totalAmountOfAllSales'],
                    'total_sales' => $salesReport['totalSales'],
                    'total_purchase' => $purchaseReport['totalPurchase'] ?? 0,
                    'total_purchase_amount' => 0,
                    'recent_expired_products' => $lastFiveExpiredProducts,
                    'recent_out_of_stock_products' => $lastFiveOutOfStockProducts,
                    'recent_damaged_products' => $lastFiveDamagedProducts,
                    'recent_purchased_products' => 0,
                    'today_sales_total_amount' => $salesReport['todaySalesTotalAmount'],
                    'last_week_sales_total_amount' => $salesReport['lastDaysSalesTotalAmount'],
                    'last_months_sales_total_amount' => $salesReport['lastWeeksSalesTotalAmount'],
                    'last_years_sales_total_amount' => $salesReport['lastMonthsSalesTotalAmount'],
                ],
            ]);
        } catch (HttpException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], $e->getStatusCode());
        } catch (Exception $e) {
            Log::error('Report generation error', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function report()
    {
        try {
            return response()->json([
                'message' => "Report fetched successfully",
                'status' => "success",
                'success' => true,
                'data' => [],
            ]);
        } catch (Exception $th) {
            return response()->json([
                'message' => "Something went wrong. Try again later",
                'status' => "error",
                'success' => false
            ]);
        }
    }

    // public function getInventoryRecords(Request $request)
    // {
    //     $query = Product::with(['addedBy', 'lastUpdatedBy', 'type', 'manufacturer'])
    //         ->where('status', 'AVAILABLE');

    //     if ($request->filled('status')) {
    //         $query->where('status', $request->input('status'));
    //     }

    //     if ($request->filled('q')) {
    //         $searchQuery = $request->input('q');
    //         $query->where(function ($q) use ($searchQuery) {
    //             $q->where('brand_name', 'like', "%{$searchQuery}%")
    //                 ->orWhere('generic_name', 'like', "%{$searchQuery}%");
    //         });
    //     }

    //     $request = $query->orderBy('updated_at', 'desc')->paginate($request->input('limit', 50));

    //     return response()->json([
    //         'message' => 'All products fetched successfully',
    //         'status' => 'success',
    //         'success' => true,
    //         'data' => $request,
    //     ]);
    // }

    public function getInventoryRecords(Request $request)
    {
        $query = Product::with(['addedBy', 'lastUpdatedBy', 'type', 'manufacturer']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if (!$request->filled('status')) {
            $query->where('status', 'AVAILABLE');
        }

        if ($request->filled('q')) {
            $searchQuery = $request->input('q');
            $query->where(function ($q) use ($searchQuery) {
                $q->where('brand_name', 'like', "%{$searchQuery}%")
                    ->orWhere('generic_name', 'like', "%{$searchQuery}%");
            });
        }

        $products = $query->orderByDesc('updated_at')
            ->paginate($request->input('limit', 50));

        return response()->json([
            'message' => 'All products fetched successfully',
            'status' => 'success',
            'success' => true,
            'data' => $products,
        ]);
    }

    public function delete($id)
    {
        try {
            $product = Product::find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 400);
            }

            $product->deleted_by_id = Auth::user()->id;
            $product->save();
            $product->delete();

            return response()->json(['message' => 'Product deleted successfully']);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function findOne($id)
    {
        try {
            $product = Product::with(['manufacturer', 'addedBy'])->find($id);
            if (!$product) {
                return response()->json(['message' => 'Product not found'], 400);
            }

            return response()->json(['message' => 'Product deleted successfully', 'data' => $product]);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function getOutOfStockProducts(Request $request)
    {
        return $this->fetchProductsByStatus($request, 'OUT_OF_STOCK');
    }

    public function getExpiredProducts(Request $request)
    {
        return $this->fetchProductsByStatus($request, "EXPIRED");
    }

    public function getDamagedProducts(Request $request)
    {
        return $this->fetchProductsByStatus($request, "DAMAGED");
    }

    private function fetchProductsByStatus(Request $request, $status)
    {
        try {
            $limit = (int) $request->query('limit', 15);
            $searchQuery = $request->query('q');

            $query = Product::with(['addedBy', 'type', 'manufacturer'])
                ->where('status', $status);

            if (!empty($searchQuery)) {
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('brand_name', 'like', "%$searchQuery%")
                        ->orWhere('generic_name', 'like', "%$searchQuery%");
                });
            }

            $products = $query->orderByDesc('created_at')
                ->paginate($limit);


            return response()->json([
                'message' => 'All products fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $products
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch products: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'brand_name' => ['required', 'string'],
            'expiry_date' => ['required', 'date'],
            'manufacturing_date' => ['required', 'date'],
            'dosage_strength' => ['required', 'string'],
            'unit_price' => ['required', 'numeric', 'max:999999.999'],
            'dosage_type' => ['required', 'string'],
            // 'quantity_purchase' => ['required', 'numeric', 'max:999999.999'],
            'stock_alert_threshold' => ['nullable', 'numeric', 'min:0'],
            // Optional fields
            'generic_name' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'manufacturer' => ['nullable', 'numeric', 'exists:product_manufacturers,id'],
            'storage_condition' => ['nullable', 'string', 'max:255'],
            'dimension' => ['nullable', 'string'],
            'nafdac_code' => ['nullable', 'string'],
            'weight' => ['nullable', 'string'],
            'purchase_price' => ['nullable', 'numeric', 'max:999999.999'],
            'batch_code' => ['nullable', 'string'],
        ]);

        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'message' => 'Product detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $fieldMapping = [
                'brand_name' => 'brand_name',
                'generic_name' => 'generic_name',
                'description' => 'description',
                'manufacturer' => 'manufacturer_id',
                'storage_condition' => 'storage_condition',
                'dosage_type' => 'dosage_type',
                'expiry_date' => 'expiry_date',
                'manufacturing_date' => 'manufacturing_date',
                'dimension' => 'dimension',
                'nafdac_code' => 'nafdac_code',
                'dosage_strength' => 'dosage_strength',
                'weight' => 'weight',
                'unit_price' => 'unit_price',
                'purchase_price' => 'purchase_price',
                // 'quantity_purchase' => 'quantity_purchase',
                'batch_code' => 'batch_code',
                'stock_alert_threshold' => 'stock_alert_threshold',
            ];

            $user = Auth::user();
            $userId = $user->id;
            $updateData = [];
            $changes = [];

            foreach ($fieldMapping as $requestKey => $dbColumn) {
                if (!array_key_exists($requestKey, $validated)) {
                    continue; // Skip if field not present in validated request
                }

                $oldValue = $product->$dbColumn;
                $newValue = $validated[$requestKey];

                if ((string) $oldValue !== (string) $newValue) {
                    $changes[$dbColumn] = [
                        'old' => $oldValue,
                        'new' => $newValue,
                    ];
                    $updateData[$dbColumn] = $newValue;
                }
            }

            // Special computed field: quantity_in_stock = quantity_purchase - quantity_available_for_sales
            if (isset($validated['quantity_purchase'])) {
                $computedStock = $validated['quantity_purchase'] - $product->quantity_available_for_sales;
                if ((string) $computedStock !== (string) $product->quantity_in_stock) {
                    $changes['quantity_in_stock'] = [
                        'old' => $product->quantity_in_stock,
                        'new' => $computedStock,
                    ];
                    $updateData['quantity_in_stock'] = $computedStock;
                }
            }

            // Ensure sales_price is synced with unit_price
            if (isset($validated['unit_price']) && (string) $validated['unit_price'] !== (string) $product->sales_price) {
                $changes['sales_price'] = [
                    'old' => $product->sales_price,
                    'new' => $validated['unit_price'],
                ];
                $updateData['sales_price'] = $validated['unit_price'];
            }

            $userId = Auth::id();
            $user = Auth::user();
            $updateData['last_updated_on'] = now();
            $updateData['last_updated_by_id'] = $userId;

            // If there are stock-sensitive updates
            if (
                array_key_exists('quantity_in_stock', $changes) ||
                array_key_exists('quantity_purchase', $changes) ||
                array_key_exists('unit_price', $changes)
            ) {
                $updateData['stock_updated_on'] = now();
                $updateData['stock_last_updated_by'] = $userId;
            }

            // Merge audit log
            if (!empty($changes)) {
                $existingAudit = json_decode($product->audit_log ?? '[]', true);
                $existingAudit[] = [
                    'changed_by' => [
                        'id' => $userId,
                        'name' => $user->name,
                    ],
                    'changes' => $changes,
                    'changed_at' => now()->toDateTimeString(),
                ];
                $updateData['audit_log'] = json_encode($existingAudit);
            }

            // Perform the update
            $product->update($updateData);

            return response()->json([
                'message' => 'Product updated successfully',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (Exception $e) {
            Log::error('Product update error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function update(Request $request, int $id)
    // {
    //     $validated = $request->validate([
    //         'brand_name' => ['required', 'string'],
    //         'expiry_date' => ['required', 'date'],
    //         'manufacturing_date' => ['required', 'date'],
    //         'dosage_strength' => ['required', 'string'],
    //         'unit_price' => ['required', 'numeric', 'max:999999.999'],
    //         'dosage_type' => ['required', Rule::in(['TABLET', 'CAPSULE', 'DROP', 'LIQUID', 'OINTMENT', 'CREAM', 'SYRUP', 'INJECTION'])],
    //         'quantity_purchase' => ['required', 'numeric', 'max:999999.999'],
    //         'stock_alert_threshold' => ['required', 'integer', 'min:0', 'lte:quantity_purchase'],
    //         // Optional or nullable fields
    //         'generic_name' => ['nullable', 'string'],
    //         'description' => ['nullable', 'string'],
    //         'manufacturer' => ['nullable', 'numeric', 'exists:product_manufacturers,id'],
    //         'storage_condition' => ['nullable', 'string', 'max:255'],
    //         'dimension' => ['nullable', 'string'],
    //         'nafdac_code' => ['nullable', 'string'],
    //         'weight' => ['nullable', 'string'],
    //         'purchase_price' => ['nullable', 'numeric', 'max:999999.999'],
    //         'batch_code' => ['nullable', 'string'],
    //     ]);

    //     try {
    //         $product = Product::find($id);

    //         if (!$product) {
    //             return response()->json([
    //                 'message' => 'Product detail not found',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         // Check for conflicting product (same NAFDAC or batch code)
    //         $conflict = Product::where('id', '!=', $id)
    //             ->where(function ($q) use ($validated) {
    //                 $q->where('nafdac_code', $validated['nafdac_code'])
    //                     ->orWhere('batch_code', $validated['batch_code']);
    //             })
    //             ->first();

    //         if ($conflict) {
    //             if ($conflict->nafdac_code === $validated['nafdac_code']) {
    //                 return response()->json([
    //                     'message' => 'A product with the same NAFDAC code already exists',
    //                     'success' => false,
    //                     'status' => 'error',
    //                 ], 400);
    //             }

    //             if ($conflict->batch_code === $validated['batch_code']) {
    //                 return response()->json([
    //                     'message' => 'A product with the same batch code already exists',
    //                     'success' => false,
    //                     'status' => 'error',
    //                 ], 400);
    //             }
    //         }

    //         // Check for duplicate product with same brand, generic name, and manufacturer
    //         $duplicate = Product::where('id', '!=', $id)
    //             ->where('brand_name', $validated['brand_name'])
    //             ->where('generic_name', $validated['generic_name'])
    //             ->where('manufacturer_id', $validated['manufacturer'])
    //             ->first();

    //         if ($duplicate) {
    //             return response()->json([
    //                 'message' => 'A product with the same brand name, generic name, and manufacturer already exists',
    //                 'success' => false,
    //                 'status' => 'error',
    //             ], 400);
    //         }

    //         $product->update([
    //             'brand_name' => strtolower(trim($validated['brand_name'])),
    //             'generic_name' => strtolower(trim($validated['generic_name'])),
    //             'description' => strtolower(trim($validated['description'])),
    //             'manufacturer_id' => $validated['manufacturer'],
    //             // 'product_type_id' => $validated['type'],
    //             'stock_alert_threshold' => $request['stock_alert_threshold'],
    //             'nafdac_code' => $validated['nafdac_code'],
    //             'batch_code' => strtolower(trim($validated['batch_code'] ?? '')),
    //             'dimension' => strtolower(trim($validated['dimension'] ?? '')),
    //             'expiry_date' => $validated['expiry_date'],
    //             'manufacturing_date' => $validated['manufacturing_date'],
    //             'dosage_type' => $validated['dosage_type'],
    //             'dosage_strength' => $validated['dosage_strength'],
    //             'unit_price' => $validated['unit_price'],
    //             'purchase_price' => $validated['purchase_price'],
    //             'quantity_purchase' => $validated['quantity_purchase'],
    //             'quantity_in_stock' => $validated['quantity_purchase'] - $product->quantity_available_for_sales,
    //             'sales_price' => $validated['unit_price'],
    //             'weight' => $validated['weight'],
    //             'storage_condition' => $validated['storage_condition'],
    //             'last_updated_on' => now(),
    //             'last_updated_by_id' => Auth::id(),
    //         ]);

    //         // // Sync categories
    //         // $product->categories()->sync($validated['categories']);

    //         return response()->json([
    //             'message' => 'Product detail updated successfully',
    //             'status' => 'success',
    //             'success' => true,
    //         ]);
    //     } catch (Exception $e) {
    //         Log::info($e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'success' => false,
    //             'status' => 'error',
    //         ], 500);
    //     }
    // }

    public function markExpired($id)
    {
        return $this->updateStatus($id, 'EXPIRED');
    }

    public function unMarkExpired($id)
    {
        return $this->updateStatus($id, 'AVAILABLE');
    }

    public function markDamaged($id)
    {
        return $this->updateStatus($id, 'DAMAGED');
    }

    public function markOutOfStock($id)
    {
        return $this->updateStatus($id, 'OUT_OF_STOCK');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:2048',
        ]);

        try {
            Log::info("hey");
            Excel::import(new ProductImport, $request->file('file'));

            return response()->json([
                'message' => 'Products imported successfully',
                'success' => true,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function unMarkDamaged($id)
    {
        return $this->updateStatus($id, 'AVAILABLE');
    }

    public function unMarkOutOfStock($id)
    {
        return $this->updateStatus($id, 'AVAILABLE');
    }

    private function updateStatus(int $id, string $status)
    {
        try {
            $validStatuses = ['AVAILABLE', 'EXPIRED', 'DAMAGED', 'OUT_OF_STOCK'];

            // Validate the new status
            if (!in_array($status, $validStatuses)) {
                return response()->json([
                    'message' => 'Invalid status value provided',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Find the product
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            // Check if the status is already the same
            if ($product->status === $status) {
                return response()->json([
                    'message' => 'The product already has the status "' . $status . '"',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            // Update the status
            $product->status = $status;
            $product->last_updated_on = now();
            $product->last_updated_by_id = Auth::id();
            $product->is_available = $status === "AVAILABLE";
            $product->save();

            return response()->json([
                'message' => 'Product status updated successfully to "' . $status . '"',
                'success' => true,
                'status' => 'success',
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
}
