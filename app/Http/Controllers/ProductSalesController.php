<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Patient;
use App\Models\Product;
use App\Models\ProductSales;
use App\Models\ProductSalesItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Throwable;

class ProductSalesController extends Controller
{
    protected PaymentController $paymentController;

    public function __construct(PaymentController $paymentController)
    {
        $this->paymentController = $paymentController;
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'is_patient' => 'required|boolean',
            'patient_id' => 'nullable|exists:patients,id',
            'customer_name' => 'nullable|string',
            'cart' => 'required|array',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.quantity' => 'required|numeric|min:1',
        ]);

        try {
            $staff = Auth::user();

            DB::beginTransaction();

            $patient = null;
            if ($data['is_patient']) {
                $patient = Patient::find($data['patient_id']);
                if (!$patient) {
                    throw new BadRequestHttpException('Patient not found');
                }
            }

            $cartItems = [];
            $errorMessages = [];

            foreach ($data['cart'] as $index => $cartItem) {
                $product = Product::find($cartItem['product_id']);

                if (!$product) {
                    $errorMessages[] = "Product {$index} record not found";
                    continue;
                }

                $saleItem = new ProductSalesItem();
                $saleItem->amount = bcmul($product->unit_price, $cartItem['quantity'], 2);
                $saleItem->quantity_sold = $cartItem['quantity'];
                $saleItem->product_id = $product->id;
                $saleItem->save();
                $cartItems[] = $saleItem;
            }

            Log::info($data['is_patient']);

            if (!empty($errorMessages)) {
                DB::rollBack();
                return response()->json([
                    'messages' => $errorMessages,
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $saleRecord = new ProductSales();
            $saleRecord->customer_name = $data['is_patient'] ? $patient->fullname : $data['customer_name'];
            $saleRecord->sold_by_id = $staff->id;
            $saleRecord->invoice_id = strtoupper(Str::random(10));
            $saleRecord->total_price = array_reduce($cartItems, fn($total, $item) => bcadd($total, $item->amount, 2), '0.00');
            $saleRecord->patient_id = $data['is_patient'] ? $patient->id : null;
            $saleRecord->status = 'CREATED';
            $saleRecord->type = 'point-of-sales';
            $saleRecord->history = json_encode([['title' => 'CREATED', 'date' => now()]]);
            $saleRecord->save();

            // Explicitly link sales items to sale record
            foreach ($cartItems as $saleItem) {
                $saleItem->product_sales_id = $saleRecord->id;
                $saleItem->save();
            }

            // create the payment record
            $payment = $this->paymentController->createPharmacySalesPayment(
                $saleRecord,
                $saleRecord->customer_name,
                $data['is_patient'] ? $patient->id : null
            );

            DB::commit();

            $saleRecord->load(['soldBy', 'patient', 'salesItems.product', 'payment.confirmedBy']);
            $payment->load(['confirmedBy']);

            return response()->json([
                'message' => 'Sales Created successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'ProductSales' => $saleRecord,
                ],
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
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
            $limit = (int) $request->query('limit', 50);
            $searchQuery =  $request->input('q', '');
            $status =  $request->input('status', '');
            $queryBuilder = ProductSales::with([
                'soldBy',
                'patient',
                'salesItems.product',
                'payment.confirmedBy.assignedBranch'
            ]);

            if (!empty($status)) {
                $queryBuilder->when($status, function ($sales, $stat) {
                    $sales->whereHas('payment', function ($q) use ($stat) {
                        $q->where('status', $stat);
                    });
                });
            }

            if (!empty($searchQuery)) {
                $queryBuilder->when($searchQuery, function ($query, $q) {
                    $query->whereHas('patient', function ($patientQuery) use ($q) {
                        $patientQuery->where(function ($qb) use ($q) {
                            $qb->where('firstname', 'like', "%{$q}%")
                                ->orWhere('lastname',  'like', "%{$q}%")
                                ->orWhere('phone_number', 'like', "%{$q}%")
                                ->orWhere('patient_reg_no', 'like', "%{$q}%");
                        });
                    });
                });
            }

            $sales = $queryBuilder->orderBy('updated_at', 'DESC')->paginate($limit);

            return response()->json([
                'message' => 'Sales Records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $sales
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

    public function downloadReceipt($id)
    {
        try {
            $ProductSales = ProductSales::with([
                'soldBy',
                'patient',
                'salesItems.product',
                'payment.confirmedBy.assignedBranch'
            ])->find($id);

            Log::info($ProductSales);

            if (!$ProductSales) {
                response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if (!$ProductSales->payment || !$ProductSales->payment->status === "SUCCESSFUL") {
                response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $ProductSales->load(['soldBy', 'salesItems.product', 'payment.confirmedBy.assignedBranch']);

            if (!View::exists('receipts.product-sales')) {
                throw new Exception("receipt not found");
            }

            Log::info($ProductSales);

            $pdf = Pdf::loadView('receipts.product-sales', [
                ...$ProductSales->toArray(),
            ]);

            $customPaper = array(0, 0, 3.15 * 72, 400);

            $pdf->setPaper($customPaper, 'portrait');

            return $pdf->stream('receipt.pdf');
        } catch (Exception $e) {
            Log::error('Failed to fetch payment detail: ' . $e->getMessage());

            response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function delete($id)
    {
        try {
            $productSales = ProductSales::find($id);
            if (!$productSales) {
                return response()->json(['message' => 'Product sales record not found'], 400);
            }

            $productSales->deleted_by_id = Auth::user()->id;
            $productSales->save();
            $productSales->delete();

            return response()->json(['message' => 'Product sales record deleted successfully']);
        } catch (Exception $th) {
            Log::info($th);

            return response()->json([
                'message' => 'Something went wrong',
                'status' => 'error',
                'success' => false
            ]);
        }
    }

    public function createPrescriptionSalesRecord($cartItems, $patientFullname, $patientId)
    {
        try {
            // $staff = Auth::user();

            $saleRecord = new ProductSales();
            $saleRecord->customer_name = $patientFullname;
            // $saleRecord->sold_by_id = $staff->id;
            $saleRecord->invoice_id = strtoupper(Str::random(10));
            $saleRecord->total_price = array_reduce($cartItems, fn($total, $item) => bcadd($total, $item->amount, 2), '0.00');
            $saleRecord->patient_id = $patientId;
            $saleRecord->status = 'CREATED';
            $saleRecord->type = 'prescription';
            $saleRecord->history = json_encode([['title' => 'CREATED', 'date' => now()]]);
            $saleRecord->save();

            foreach ($cartItems as $saleItem) {
                $saleItem->product_sales_id = $saleRecord->id;
                $saleItem->save();
            }

            // Link the sales to payment
            $this->paymentController->createPharmacySalesPayment(
                $saleRecord,
                $patientFullname,
                $patientId
            );

            return $saleRecord;
        } catch (Exception $th) {
            throw $th;
        }
    }

    public function getReport()
    {
        try {

            $data = $this->generateReport();

            return response()->json([
                'message' => 'Sales report fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data
            ]);
        } catch (Exception $error) {
            Log::info($error->getMessage());

            if ($error instanceof BadRequestHttpException) {
                return response()->json([
                    'message' => $error->getMessage(),
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function generateReport(): array
    {
        $today = Carbon::now();
        $yesterday = $today->copy()->subDay();
        $sevenDaysAgo = $today->copy()->subDays(7);
        $thirtyDaysAgo = $today->copy()->subDays(30);
        $elevenMonthsAgo = $today->copy()->subMonths(11);

        // Total sales count
        $totalSales = ProductSales::count();

        // Total paid sales
        $totalAmountOfAllSales = ProductSales::whereHas('payment', function ($query) {
            $query->where('status', PaymentStatus::COMPLETED->value);
        })->sum('total_price');

        // Sales over different time periods
        $todaySales = $this->sumSalesAfter($yesterday);
        $sevenDaysAgoSales = $this->sumSalesAfter($sevenDaysAgo);
        $thirtyDaysAgoSales = $this->sumSalesAfter($thirtyDaysAgo);
        $elevenMonthsAgoSales = $this->sumSalesAfter($elevenMonthsAgo);

        // Grouped chart data
        $saleTransactions = DB::table('product_sales as sales')
            ->join('payments as payment', 'sales.payment_id', '=', 'payment.id')
            ->selectRaw('
                MONTH(sales.created_at) as month,
                YEAR(sales.created_at) as year,
                SUM(CAST(payment.amount AS DECIMAL(10, 3))) as totalAmount,
                CASE
                    WHEN payment.status IN (?, ?) THEN "PAID"
                    ELSE "PENDING"
                END as statusGroup
            ', [PaymentStatus::COMPLETED->value, "CREATED"])
            ->whereBetween('sales.created_at', [$elevenMonthsAgo, $yesterday])
            ->groupByRaw('YEAR(sales.created_at), MONTH(sales.created_at), statusGroup')
            ->orderByRaw('YEAR(sales.created_at) DESC, MONTH(sales.created_at) DESC')
            ->get();

        $chartData = app(PaymentController::class)->formatData($saleTransactions, $today, $elevenMonthsAgo);

        // You can replace this with a proper query later
        $lastFiveProductSales = ProductSales::latest()->take(5)->get();

        return [
            'lastFiveProductSales' => $lastFiveProductSales,
            'totalSales' => $totalSales,
            'totalAmountOfAllSales' => $totalAmountOfAllSales,
            'todaySalesTotalAmount' => $todaySales,
            'lastDaysSalesTotalAmount' => $sevenDaysAgoSales,
            'lastWeeksSalesTotalAmount' => $thirtyDaysAgoSales,
            'lastMonthsSalesTotalAmount' => $elevenMonthsAgoSales,
            'chart_data' => $chartData,
        ];
    }
    protected function sumSalesAfter(Carbon $date): float
    {
        return ProductSales::whereHas('payment', function ($query) {
            $query->where('status', PaymentStatus::COMPLETED->value);
        })->where('created_at', '>', $date)->sum('total_price');
    }
}
