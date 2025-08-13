<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Http\Requests\CreatePaymentRequest;
use App\Models\Admission;
use App\Models\AnteNatal;
use App\Models\LabRequest;
use App\Models\OrganisationAndHmo;
use App\Models\OrganisationAndHmoPayment;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSales;
use App\Models\RadiologyRequest;
use App\Models\Service;
use App\Models\Treatment;
use App\Models\Visitation;
use App\Models\WalletTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class PaymentController extends Controller
{
    public function findAll(Request $request)
    {
        try {
            $limit = $request->input('limit', 50);
            $status = $request->input('status');
            $searchQuery = $request->input('q');
            $from = $request->input('from');
            $to = $request->input('to');

            // Base query for fetching paginated payment data
            $queryBuilder = Payment::with([
                'addedBy:id,firstname,lastname',
                'lastUpdatedBy:id,firstname,lastname',
                'patient:id,firstname,lastname,patient_reg_no'
            ])->orderBy('updated_at', 'DESC');

            // Apply filters for status
            if (!empty($status)) {
                $queryBuilder->where('status', $status);
            }

            // Apply filters for search query
            if (!empty($searchQuery)) {
                $queryBuilder->where(function ($qb) use ($searchQuery) {
                    $qb->where('transaction_reference', 'like', "%$searchQuery%")
                        ->orWhere('reference', 'like', "%$searchQuery%")
                        ->orWhereHas('patient', function ($q) use ($searchQuery) {
                            $q->where('firstname', 'like', "%$searchQuery%")
                                ->orWhere('lastname', 'like', "%$searchQuery%")
                                ->orWhere('patient_reg_no', 'like', "%$searchQuery%")
                                ->orWhere('phone_number', 'like', "%$searchQuery%");
                        });
                });
            }

            // Apply date filters
            if (!empty($from)) {
                $queryBuilder->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            }
            if (!empty($to)) {
                $queryBuilder->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            }

            // Clone the query to calculate totals (excluding eager loads and ordering)
            $totalsQuery = Payment::query();

            if (!empty($status)) {
                $totalsQuery->where('status', $status);
            }

            if (!empty($searchQuery)) {
                $totalsQuery->where(function ($qb) use ($searchQuery) {
                    $qb->where('transaction_reference', 'like', "%$searchQuery%")
                        ->orWhere('reference', 'like', "%$searchQuery%")
                        ->orWhereHas('patient', function ($q) use ($searchQuery) {
                            $q->where('firstname', 'like', "%$searchQuery%")
                                ->orWhere('lastname', 'like', "%$searchQuery%")
                                ->orWhere('patient_reg_no', 'like', "%$searchQuery%")
                                ->orWhere('phone_number', 'like', "%$searchQuery%");
                        });
                });
            }

            if (!empty($from)) {
                $totalsQuery->where('created_at', '>=', Carbon::parse($from)->startOfDay());
            }

            if (!empty($to)) {
                $totalsQuery->where('created_at', '<=', Carbon::parse($to)->endOfDay());
            }

            $totals = $totalsQuery->selectRaw('
            SUM(CASE WHEN status = "CREATED" THEN amount ELSE 0 END) as created,
            SUM(CASE WHEN status = "COMPLETED" THEN amount ELSE 0 END) as completed,
            SUM(CASE WHEN status = "PENDING" THEN amount ELSE 0 END) as pending
        ')->first();

            // Paginated result
            $data = $queryBuilder->paginate($limit);

            return response()->json([
                'message' => 'Payments fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'pagination' => $data,
                    'total_amount_created' => $totals->created ?? 0,
                    'total_amount_completed' => $totals->completed ?? 0,
                    'total_amount_pending' => $totals->pending ?? 0,
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Payment fetch error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function findAllOrganisationPayments(Request $request)
    {
        try {
            $limit = $request->input('limit', 50);
            $status = $request->input('status');
            $searchQuery = $request->input('q');

            $queryBuilder = OrganisationAndHmo::with([
                'addedBy:id,firstname,lastname,email,role',
                'lastUpdatedBy:id,firstname,lastname,email,role',
            ]);

            if (!empty($status)) {
                $queryBuilder->where('status', $status);
            }

            if (!empty($searchQuery)) {
                $queryBuilder->where('name', 'LIKE', "%{$searchQuery}%");
            }

            $paginated = $queryBuilder->orderBy('updated_at', 'DESC')->paginate($limit);

            // Get HMO IDs from paginated records
            $hmoIds = $paginated->getCollection()->pluck('id')->unique()->filter()->values()->all();

            // Calculate all balances in batch
            $balances = $this->calculateOutstandingBalanceBatch($hmoIds);

            // Attach financials to each item
            $paginated->getCollection()->transform(function ($record) use ($balances) {
                Log::info($balances);
                $hmoId = $record->id;

                $record->totalDue = $balances[$hmoId]['totalDue'] ?? 0;
                $record->amountPaid = $balances[$hmoId]['totalPaid'] ?? 0;
                $record->outstandingBalance = $balances[$hmoId]['outstandingBalance'] ?? 0;

                return $record;
            });

            return response()->json([
                'message' => 'Payments fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $paginated
            ]);
        } catch (Exception $e) {
            Log::error('Organisation payment fetch error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getStatsReport(Request $request)
    {
        try {
            $request->validate([
                'from' => 'nullable|date',
                'to' => 'nullable|date|after_or_equal:from',
            ]);

            $from = $request->query('from');
            $to = $request->query('to');

            $startDate = $from ? Carbon::parse($from) : Carbon::today()->subMonths(12);
            $endDate = $to ? Carbon::parse($to) : Carbon::yesterday();

            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $sevenDaysAgo = $today->copy()->subDays(7);
            $thirtyDaysAgo = $today->copy()->subDays(30);

            // Helper closures - updated to calculate amounts correctly
            $getCompletedAmount = fn($start, $end) => Payment::where('status', 'COMPLETED')
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            $getPendingAmount = fn($start, $end) => Payment::whereIn('status', ['PENDING', 'CREATED'])
                ->whereBetween('created_at', [$start, $end])
                ->sum('amount');

            $getCountByStatus = fn(array $statuses, $start, $end) => Payment::whereIn('status', $statuses)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            // Total stats - corrected calculations
            $totalPaid = $getCountByStatus(['COMPLETED'], $startDate, $endDate);
            $totalRevenue = $getCompletedAmount($startDate, $endDate);
            $totalPendingCount = $getCountByStatus(['PENDING', 'CREATED'], $startDate, $endDate);
            $totalPendingAmount = $getPendingAmount($startDate, $endDate);
            $totalFailed = $getCountByStatus(['FAILED'], $startDate, $endDate);

            // Time blocks - updated to use correct calculations
            $buildPeriodSummary = fn($start, $end) => [
                'paid_count' => $getCountByStatus(['COMPLETED'], $start, $end),
                'paid_amount' => $getCompletedAmount($start, $end),
                'pending_count' => $getCountByStatus(['PENDING', 'CREATED'], $start, $end),
                'pending_amount' => $getPendingAmount($start, $end),
            ];

            $todaySummary = $buildPeriodSummary($today, $today);
            $lastWeekSummary = $buildPeriodSummary($sevenDaysAgo, $yesterday);
            $lastMonthSummary = $buildPeriodSummary($thirtyDaysAgo, $yesterday);

            // Chart Data - unchanged
            $transactions = Payment::selectRaw('
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            SUM(CAST(amount AS DECIMAL(10,3))) as totalAmount,
            CASE 
                WHEN status = "COMPLETED" THEN "COMPLETED"
                ELSE "PENDING"
            END as statusGroup
        ')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('year', 'month', 'statusGroup')
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get();

            $chartData = $this->formatData($transactions, $today, $startDate);

            // Grouped summary by type - unchanged
            $groupedTransactionSummary = Payment::selectRaw('
            type,
            SUM(CASE WHEN status IN ("PENDING", "CREATED") THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN status = "COMPLETED" THEN amount ELSE 0 END) as completed_amount
        ')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy('type')
                ->get();

            $recentTransactions = Payment::with(['addedBy', 'lastUpdatedBy', 'deletedBy', 'patient'])
                ->orderBy('updated_at', 'desc')
                ->limit(5)
                ->get();

            return response()->json([
                'message' => 'All payments detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'chart_data' => $chartData,
                    'group_data' => $groupedTransactionSummary,
                    'total_transactions' => $totalPaid + $totalPendingCount + $totalFailed,
                    'total_failed_transactions' => $totalFailed,
                    'total_pending_transactions' => $totalPendingCount,
                    'total_pending_amount' => $totalPendingAmount, // Added this field
                    'total_paid_transactions' => $totalPaid,
                    'total_revenue' => $totalRevenue,
                    'today_total_transactions' => $todaySummary['paid_count'] + $todaySummary['pending_count'],
                    'today_total_pending_transactions' => $todaySummary['pending_count'],
                    'today_total_pending_amount' => $todaySummary['pending_amount'], // Added this field
                    'today_total_paid_transactions' => $todaySummary['paid_count'],
                    'today_total_revenue' => $todaySummary['paid_amount'],
                    'last_week_total_transactions' => $lastWeekSummary['paid_count'] + $lastWeekSummary['pending_count'],
                    'last_week_total_pending_transactions' => $lastWeekSummary['pending_count'],
                    'last_week_total_pending_amount' => $lastWeekSummary['pending_amount'], // Added this field
                    'last_week_total_paid_transactions' => $lastWeekSummary['paid_count'],
                    'last_week_total_revenue' => $lastWeekSummary['paid_amount'],
                    'last_month_total_transactions' => $lastMonthSummary['paid_count'] + $lastMonthSummary['pending_count'],
                    'last_month_total_pending_transactions' => $lastMonthSummary['pending_count'],
                    'last_month_total_pending_amount' => $lastMonthSummary['pending_amount'], // Added this field
                    'last_month_total_paid_transactions' => $lastMonthSummary['paid_count'],
                    'last_month_total_revenue' => $lastMonthSummary['paid_amount'],
                    'recent_transactions' => $recentTransactions,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch payment stats: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    // public function getStatsReport(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'from' => 'nullable|date',
    //             'to' => 'nullable|date|after_or_equal:from',
    //         ]);

    //         $from = $request->query('from');
    //         $to = $request->query('to');

    //         $startDate = $from ? Carbon::parse($from) : Carbon::today()->subMonths(11);
    //         $endDate = $to ? Carbon::parse($to) : Carbon::yesterday();

    //         $today = Carbon::today();
    //         $yesterday = Carbon::yesterday();
    //         $sevenDaysAgo = $today->copy()->subDays(7);
    //         $thirtyDaysAgo = $today->copy()->subDays(30);

    //         // Helper closures
    //         $getCompletedTransactions = fn($start, $end) => Payment::where('status', 'COMPLETED')
    //             ->whereBetween('created_at', [$start, $end])
    //             ->get(['amount']);

    //         $getCountByStatus = fn(array $statuses, $start, $end) => Payment::whereIn('status', $statuses)
    //             ->whereBetween('created_at', [$start, $end])
    //             ->count();

    //         $sumAmounts = fn($collection) => $collection->sum(fn($t) => floatval($t->amount));

    //         // Total stats
    //         $completedTransactions = $getCompletedTransactions($startDate, $endDate);
    //         $totalPaid = $completedTransactions->count();
    //         $totalRevenue = $sumAmounts($completedTransactions);
    //         $totalPending = $getCountByStatus(['PENDING', 'CREATED'], $startDate, $endDate);
    //         $totalFailed = $getCountByStatus(['FAILED'], $startDate, $endDate);

    //         // Time blocks
    //         $buildPeriodSummary = fn($start, $end) => [
    //             'paid' => $getCompletedTransactions($start, $end)->count(),
    //             'pending' => $getCountByStatus(['PENDING', 'CREATED'], $start, $end),
    //             'revenue' => $sumAmounts($getCompletedTransactions($start, $end)),
    //         ];

    //         $todaySummary = $buildPeriodSummary($yesterday, $today);
    //         $lastWeekSummary = $buildPeriodSummary($sevenDaysAgo, $yesterday);
    //         $lastMonthSummary = $buildPeriodSummary($thirtyDaysAgo, $yesterday);

    //         // Chart Data
    //         $transactions = Payment::selectRaw('
    //         MONTH(created_at) as month,
    //         YEAR(created_at) as year,
    //         SUM(CAST(amount AS DECIMAL(10,3))) as totalAmount,
    //         CASE 
    //             WHEN status = "COMPLETED" THEN "COMPLETED"
    //             ELSE "PENDING"
    //         END as statusGroup
    //     ')
    //             ->whereBetween('created_at', [$startDate, $endDate])
    //             ->groupBy('year', 'month', 'statusGroup')
    //             ->orderByDesc('year')
    //             ->orderByDesc('month')
    //             ->get();

    //         $chartData = $this->formatData($transactions, $today, $startDate);

    //         // Grouped summary by type
    //         $groupedTransactionSummary = Payment::selectRaw('
    //         type,
    //         SUM(CASE WHEN status = "PENDING" THEN amount ELSE 0 END) as pending_amount,
    //         SUM(CASE WHEN status = "COMPLETED" THEN amount ELSE 0 END) as completed_amount
    //     ')
    //             ->whereBetween('created_at', [$startDate, $endDate])
    //             ->groupBy('type')
    //             ->get();

    //         $recentTransactions = Payment::with(['addedBy', 'lastUpdatedBy', 'deletedBy', 'patient'])
    //             ->orderBy('updated_at', 'desc')
    //             ->limit(5)
    //             ->get();

    //         return response()->json([
    //             'message' => 'All payments detail fetched successfully',
    //             'status' => 'success',
    //             'success' => true,
    //             'data' => [
    //                 'chart_data' => $chartData,
    //                 'group_data' => $groupedTransactionSummary,
    //                 'total_transactions' => $totalPaid + $totalPending,
    //                 'total_failed_transactions' => $totalFailed,
    //                 'total_pending_transactions' => $totalPending,
    //                 'total_paid_transactions' => $totalPaid,
    //                 'total_revenue' => $totalRevenue,
    //                 'today_total_transactions' => $todaySummary['paid'] + $todaySummary['pending'],
    //                 'today_total_pending_transactions' => $todaySummary['pending'],
    //                 'today_total_paid_transactions' => $todaySummary['paid'],
    //                 'today_total_revenue' => $todaySummary['revenue'],
    //                 'last_week_total_transactions' => $lastWeekSummary['paid'] + $lastWeekSummary['pending'],
    //                 'last_week_total_pending_transactions' => $lastWeekSummary['pending'],
    //                 'last_week_total_paid_transactions' => $lastWeekSummary['paid'],
    //                 'last_week_total_revenue' => $lastWeekSummary['revenue'],
    //                 'last_month_total_transactions' => $lastMonthSummary['paid'] + $lastMonthSummary['pending'],
    //                 'last_month_total_pending_transactions' => $lastMonthSummary['pending'],
    //                 'last_month_total_paid_transactions' => $lastMonthSummary['paid'],
    //                 'last_month_total_revenue' => $lastMonthSummary['revenue'],
    //                 'recent_transactions' => $recentTransactions,
    //             ],
    //         ]);
    //     } catch (Exception $e) {
    //         Log::error('Failed to fetch payment stats: ' . $e->getMessage());

    //         return response()->json([
    //             'message' => 'Something went wrong. Try again in 5 minutes',
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function chart(Request $request)
    {
        $from = $request->query('from');
        $to = $request->query('to');

        $startDate = $from ? Carbon::parse($from) : Carbon::today()->subMonths(11);
        $endDate = $to ? Carbon::parse($to) : Carbon::yesterday();
        $today = Carbon::today();

        $transactions = Payment::selectRaw('
            MONTH(created_at) as month,
            YEAR(created_at) as year,
            SUM(CAST(amount AS DECIMAL(10,3))) as totalAmount,
            CASE 
                WHEN status = "COMPLETED" THEN "COMPLETED"
                ELSE "PENDING"
            END as statusGroup
        ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('year', 'month', 'statusGroup')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $chartData = $this->formatChartData($transactions, $today, $startDate);

        return response()->json([
            'message' => 'Chart data fetched',
            'success' => true,
            'status' => 'success',
            'data' => $chartData
        ]);
    }

    private function formatChartData($transactions, $today, $startDate)
    {
        $monthly = [];

        for ($date = $startDate->copy(); $date <= $today; $date->addMonth()) {
            $year = $date->year;
            $month = $date->month;

            $completed = $transactions->firstWhere(fn($t) => $t->year == $year && $t->month == $month && $t->statusGroup == 'COMPLETED');
            $pending = $transactions->firstWhere(fn($t) => $t->year == $year && $t->month == $month && $t->statusGroup == 'PENDING');

            $monthly[] = [
                'month' => $date->format('F'),
                'year' => $year,
                'completed' => $completed?->totalAmount ?? 0,
                'pending' => $pending?->totalAmount ?? 0,
            ];
        }

        return $monthly;
    }

    public function formatData($transactions, Carbon $today, Carbon $startDate)
    {
        // Get all months and years between the date range (from startDate to today)
        $monthsBetween = $this->getMonthsBetween($startDate, $today);

        $formattedData = array_map(function ($date) use ($transactions) {
            $month = $date['month'];
            $year = $date['year'];

            $pending = $transactions->filter(function ($transaction) use ($month, $year) {
                return $transaction->month == $month && $transaction->year == $year && $transaction->statusGroup == 'PENDING';
            })->sum('totalAmount') ?? 0;

            $paid = $transactions->filter(function ($transaction) use ($month, $year) {
                return $transaction->month == $month && $transaction->year == $year && $transaction->statusGroup == 'COMPLETED';
            })->sum('totalAmount') ?? 0;

            return [
                'month' => $month,
                'year' => $year,
                'pending' => $pending,
                'paid' => $paid
            ];
        }, $monthsBetween);

        return $formattedData;
    }

    public function getMonthlyReport()
    {
        $endDate = Carbon::now();
        $startDate = $endDate->copy()->subMonths(11)->startOfMonth();

        $report = Payment::selectRaw('
                YEAR(created_at) as year,
                MONTH(created_at) as month,
                COUNT(*) as total_completed_transactions
            ')
            ->where('status', 'COMPLETED')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Monthly report fetched successfully.',
            'data' => $report,
        ]);
    }

    private function getMonthsBetween(Carbon $startDate, Carbon $endDate)
    {
        $months = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $months[] = [
                'month' => $currentDate->month,
                'year' => $currentDate->year,
            ];
            $currentDate->addMonth();
        }

        return $months;
    }

    public function findOneByReference(string $reference)
    {

        try {
            $payment = Payment::with([
                'service',
                'addedBy',
                'lastUpdatedBy',
                'patient',
            ])->where('transaction_reference', $reference)->first();

            if (!$payment) {
                response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return [
                'message' => 'Payment detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $payment,
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch payment detail: ' . $e->getMessage());

            response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function findOne($id)
    {

        try {
            $payment = Payment::with([
                'service',
                'addedBy',
                'lastUpdatedBy',
                'patient',
            ])->find($id)->first();

            if (!$payment) {
                response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            return [
                'message' => 'Payment detail fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $payment,
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch payment detail: ' . $e->getMessage());

            response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function downloadReceipt($id)
    {
        try {
            $payment = Payment::with([
                'service',
                'addedBy',
                'lastUpdatedBy',
                'confirmedBy',
                'patient',
            ])->find($id);


            if (!$payment) {
                response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            Log::info($id . ". Payment Id: " . $payment->amount);

            $payment->load(['confirmedBy', 'patient', 'addedBy']);

            if (!View::exists('receipts.thermal')) {
                throw new Exception("receipt not found");
            }

            $pdf = Pdf::loadView('receipts.thermal', ['payment' => $payment->toArray()]);

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

    public function create(CreatePaymentRequest $request)
    {
        $data = $request->validated();
        DB::beginTransaction();

        $serviceTypeMap = [
            'LAB-TEST' => LabRequest::class,
            'TREATMENT' => Treatment::class,
            'ADMISSION' => Admission::class,
            'ACCOUNT' => Patient::class,
            'HMO' => OrganisationAndHmo::class,
            'CONSULTATION' => Visitation::class,
            'ANTE-NATAL' => AnteNatal::class,
            'RADIOLOGY-TEST' => RadiologyRequest::class,
        ];

        try {
            $staffId = Auth::user()->id;

            // Deposit can only be made for patients
            if ($data['payment_type'] == 'DEPOSIT' && empty($data['is_patient'])) {
                return response()->json([
                    'message' => 'You can only make deposit for a patient',
                    'status' => 'error',
                    'success' => false
                ], 400);
            }

            $patient = null;
            if (!empty($data['is_patient'])) {
                $patient = Patient::with('wallet')->find($data['patient_id']);
                if (!$patient) {
                    return response()->json([
                        'message' => 'Patient not found',
                        'status' => 'error',
                        'success' => false
                    ], 400);
                }
            }

            $payableType = null;
            $payableId = null;
            $payableTypeName = null;

            if ($data['payment_type'] == 'SERVICE') {
                $payableId = $data['service_id'] ?? null;
                if (!$payableId) {
                    return response()->json([
                        'message' => 'Service ID (payable_id) is required for SERVICE payments',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                // Find the service to get type and price
                $service = Service::find($payableId);
                if (!$service) {
                    return response()->json([
                        'message' => 'Invalid service selected',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                // Get payable type model from service type
                if (!array_key_exists($service->type, $serviceTypeMap)) {
                    return response()->json([
                        'message' => 'Unsupported service type for payment',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                $payableType = $serviceTypeMap[$service->type];
                $payableTypeName = $service->type;
            }

            $transactionReference = $this->generateUniqueTransactionReference();

            $payment = new Payment();
            $payment->amount = $data['payment_type'] == 'DEPOSIT'
                ? $data['amount']
                : number_format($service->price ?? 0, 3, '.', '');
            $payment->amount_payable = round($payment->amount);
            $payment->customer_name = $data['is_patient']
                ? ($patient->firstname . ' ' . $patient->lastname)
                : $data['customer_name'] ?? null;
            $payment->reference = $transactionReference;
            $payment->transaction_reference = $transactionReference;
            $payment->patient_id = $data['is_patient'] ? $patient->id : null;
            $payment->payable_type = $payableType;
            $payment->added_by_id = $staffId;
            $payment->confirmed_by_id = $staffId;
            $payment->payment_method = $data['payment_method'] == 'ACCOUNT-BALANCE' ? 'WALLET' : $data['payment_method'];
            $payment->type = $data['payment_type'] == 'DEPOSIT' ? 'DEPOSIT' : $payableTypeName;
            $payment->history = json_encode([
                ['date' => now()->toDateTimeString(), 'title' => 'CREATED'],
                ['date' => now()->toDateTimeString(), 'title' => 'PAID']
            ]);
            $payment->last_updated_by_id = $staffId;

            if ($data['payment_method'] == 'HMO') {
                $insurance = OrganisationAndHmo::find($data['hmo_id']);
                if (!$insurance) {
                    return response()->json([
                        'message' => 'Invalid HMO provider',
                        'status' => 'error',
                        'success' => false
                    ], 400);
                }

                if (!$patient || $patient->organisation_hmo_id != $insurance->id) {
                    return response()->json([
                        'message' => 'The patient does not have an account with the selected organisation or health care provider',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                $payment->payable_id = $insurance->id;
            }

            $payment->load(['addedBy', 'lastUpdatedBy', 'patient', 'payable'])->save();

            DB::commit();

            return response()->json([
                'message' => 'Payment detail created Successfully',
                'status' => 'success',
                'success' => true,
                'data' => $payment
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Payment creation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function createPharmacySalesPayment(
        $saleRecord,
        string $customerName,
        $patientId = null
    ): Payment {
        try {
            $staff = Auth::user();

            $payment = new Payment();
            $payment->amount = number_format((float) $saleRecord->total_price, 3, '.', '');
            $payment->amount_payable = (string) round((float) $payment->amount);
            $payment->customer_name = $customerName;
            $payment->history = json_encode([
                [
                    'title' => 'CREATED',
                    'date' => now(),
                    'amount' => $saleRecord->total_price,
                ],
            ]);
            $payment->transaction_reference = strtoupper(Str::random(10));
            $payment->patient_id = $patientId;
            $payment->type = 'PHARMACY';
            $payment->added_by_id = $staff->id;
            $payment->payable_type = ProductSales::class;
            $payment->payable_id = $saleRecord->id;
            $payment->save();

            return $payment;
        } catch (Exception $e) {
            log::info($e->getMessage() . "hey");
            throw new RuntimeException('Something went wrong. Try again in 5 minutes.');
        }
    }

    public function createTreatmentPayment($treatment, $totalPrice)
    {
        try {
            $staff = Auth::user();

            $treatmentPayments = Payment::where('treatment_id', $treatment->id)
                ->orderByDesc('updated_at')
                ->get();

            $payment = null;

            if ($treatmentPayments->isNotEmpty()) {
                $paidPayments = $treatmentPayments->where('status', 'COMPLETED');
                $unpaidPayments = $treatmentPayments->filter(function ($payment) {
                    return in_array($payment->status, ['CREATED', 'UPDATED']);
                });

                $paidAmount = $paidPayments->sum(function ($payment) {
                    return floatval($payment->amount);
                });

                $refundAmount = $totalPrice - $paidAmount;

                if ($refundAmount < 0) {
                    $payment = $paidPayments->first();
                    $payment->amount = number_format($totalPrice, 3, '.', '');
                    $payment->refund_amount = number_format(abs($refundAmount), 3, '.', '');
                    $payment->amount_payable = round($totalPrice);
                    $payment->history = array_merge($payment->history ?? [], [
                        ['date' => now(), 'title' => 'UPDATED'],
                        ['date' => now(), 'title' => 'COMPLETED'],
                    ]);
                    $payment->last_updated_by_id = $staff->id;
                } elseif ($refundAmount > 0 && $unpaidPayments->isNotEmpty()) {
                    $payment = $unpaidPayments->first();
                    $payment->amount = number_format($refundAmount, 3, '.', '');
                    $payment->refund_amount = null;
                    $payment->amount_payable = round($refundAmount);
                    $payment->history = array_merge($payment->history ?? [], [
                        ['date' => now(), 'title' => 'UPDATED'],
                    ]);
                    $payment->last_updated_by_id = $staff->id;
                } else {
                    $payment = new Payment([
                        'amount' => number_format($refundAmount, 3, '.', ''),
                        'amount_payable' => round($refundAmount),
                        'customer_name' => $treatment->patient->firstname . ' ' . $treatment->patient->lastname,
                        'transaction_reference' => strtoupper(Str::random(10)),
                        'patient_id' => $treatment->patient->id,
                        'treatment_id' => $treatment->id,
                        'added_by_id' => $staff->id,
                        'history' => [['date' => now(), 'title' => 'CREATED']],
                        'last_updated_by_id' => $staff->id,
                        'type' => 'TREATMENT',
                    ]);
                }
            } else {
                $payment = new Payment([
                    'amount' => number_format($totalPrice, 3, '.', ''),
                    'amount_payable' => round($totalPrice),
                    'customer_name' => $treatment->patient->firstname . ' ' . $treatment->patient->lastname,
                    'transaction_reference' => strtoupper(Str::random(10)),
                    'patient_id' => $treatment->patient->id,
                    'treatment_id' => $treatment->id,
                    'added_by_id' => $staff->id,
                    'history' => [['date' => now(), 'title' => 'CREATED']],
                    'last_updated_by_id' => $staff->id,
                    'type' => 'TREATMENT',
                ]);
            }

            $payment->save();

            $wallet = $treatment->patient->wallet;
            $wallet->outstanding_balance = number_format(floatval($wallet->outstanding_balance) + $totalPrice, 3, '.', '');
            $wallet->save();
        } catch (Exception $e) {
            Log::error('Treatment payment creation failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function markAsPaid(string $id, Request $request)
    {
        $request->validate([
            'payment_method' => [
                'required',
                Rule::in(['WALLET', 'TRANSFER', 'HMO', 'CASH']),
            ],
            'remark' => ['nullable', 'string'],
            'hmo_id' => [
                'exclude_unless:payment_method,HMO',
                'integer',
            ],
            'transfer_reference' => [
                'exclude_unless:payment_method,TRANSFER',
                'unique:payments,transaction_reference',
                'string',
            ],
            'bank_transfer_to' => [
                'exclude_unless:payment_method,TRANSFER',
                'string',
            ],
        ]);

        try {
            $staff = Auth::user();

            return DB::transaction(function () use ($id, $staff, $request) {
                $payment = Payment::with(['patient.wallet', 'patient.organisationHmo'])->find($id);

                if (!$payment) {
                    return response()->json([
                        'message' => 'The payment detail not found',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                if ($payment->status == PaymentStatus::COMPLETED->value) {
                    return response()->json([
                        'message' => 'The payment already marked as paid',
                        'status' => 'error',
                        'success' => false,
                    ], 400);
                }

                // Handle inventory update if payment is for a pharmacy
                // if ($payment->type === "PHARMACY") {
                //     $saleRecord = ProductSales::with(['payment', 'salesItems.product'])
                //         ->whereHas('payment', fn($q) => $q->where('id', $id))
                //         ->first();

                //     if ($saleRecord) {
                //         foreach ($saleRecord->salesItems as $item) {
                //             if ($item->product) {
                //                 $item->product->quantity_sold += $item->quantity_sold;
                //                 $item->product->quantity_available_for_sales -= $item->quantity_sold;
                //                 $item->product->save();
                //             }
                //         }
                //     }
                // }

                if ($payment->type === "PHARMACY") {
                    $saleRecord = ProductSales::with(['payment', 'salesItems'])
                        ->whereHas('payment', fn($q) => $q->where('id', $id))
                        ->first();

                    if ($saleRecord) {
                        foreach ($saleRecord->salesItems as $item) {
                            if (!$item->product_id) {
                                continue; // skip if product not linked
                            }

                            // Lock the product row to avoid race conditions
                            $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                            if (!$product) {
                                return response()->json([
                                    'message' => "Product not found for sales",
                                    'status' => 'error',
                                    'success' => false,
                                ], 400);
                            }

                            // Check if there's enough stock
                            if ($product->quantity_available_for_sales < $item->quantity_sold) {
                                return response()->json([
                                    'message' => "Insufficient stock for {$product->brand_name}. Available Quantity on Sales: {$product->quantity_available_for_sales}",
                                    'status' => 'error',
                                    'success' => false,
                                ], 400);
                            }

                            // Perform the update safely
                            $product->quantity_sold += $item->quantity_sold;
                            $product->quantity_available_for_sales -= $item->quantity_sold;
                            $product->save();
                        }
                    }
                }

                // Handle deposit wallet logic
                if ($payment->type == 'DEPOSIT') {
                    $wallet = $payment->patient->wallet;
                    $beforeBalance = (float) $wallet->deposit_balance;
                    $depositAmount = (float) $payment->amount_payable;
                    $outstanding = (float) ($wallet->outstanding_balance ?? 0);

                    $usedToSettleOutstanding = 0;

                    if ($outstanding > 0) {
                        if ($depositAmount >= $outstanding) {
                            $depositAmount -= $outstanding;
                            $usedToSettleOutstanding = $outstanding;
                            $wallet->outstanding_balance = 0;
                        } else {
                            $wallet->outstanding_balance -= $depositAmount;
                            $usedToSettleOutstanding = $depositAmount;
                            $depositAmount = 0;
                        }
                    }

                    $wallet->deposit_balance += $depositAmount;
                    $wallet->save();
                    $payment->save();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'payment_id' => $payment->id ?? null,
                        'transaction_type' => 'CREDIT',
                        'amount' => $payment->amount_payable,
                        'balance_before' => $beforeBalance,
                        'balance_after' => $wallet->deposit_balance,
                        'description' => 'Patient deposit',
                        'meta' => json_encode([
                            'original_deposit' => (float) $payment->amount_payable,
                            'used_to_settle_outstanding' => $usedToSettleOutstanding,
                            'credited_to_deposit_balance' => $depositAmount,
                        ]),
                    ]);
                }

                // Wallet debit
                if ($payment->patient && $request->payment_method == 'WALLET') {
                    $wallet = $payment->patient->wallet;
                    $beforeBalance = (float) $wallet->deposit_balance;
                    $amountPayable = (float) $payment->amount_payable;

                    if ($beforeBalance < $amountPayable) {
                        $outstanding = $amountPayable - $beforeBalance;
                        $wallet->deposit_balance = 0;
                        $wallet->outstanding_balance = ($wallet->outstanding_balance ?? 0) + $outstanding;
                        $afterBalance = 0;
                    } else {
                        $wallet->deposit_balance -= $amountPayable;
                        $afterBalance = $wallet->deposit_balance;
                    }

                    $wallet->save();

                    WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'payment_id' => $payment->id,
                        'transaction_type' => 'DEBIT',
                        'amount' => $amountPayable,
                        'balance_before' => $beforeBalance,
                        'balance_after' => $afterBalance,
                        'description' => 'Wallet debit for payment',
                    ]);
                }

                // HMO verification and association
                if ($request->payment_method == 'HMO') {
                    Log::info($payment->patient);
                    $patientHmoId = $payment->patient->organisationHmo->id;

                    if (!$patientHmoId || $request->hmo_id != $patientHmoId) {
                        return response()->json([
                            'message' => "The patient is not linked to the selected organization",
                            'status' => 'error',
                            'success' => false,
                        ], 400);
                    }

                    $insuranceProvider = OrganisationAndHmo::find($request->hmo_id);
                    if (!$insuranceProvider) {
                        return response()->json([
                            'message' => 'Organization Detail not found',
                            'status' => 'error',
                            'success' => false,
                        ], 400);
                    }

                    $payment->hmo_id = $insuranceProvider->id;
                }

                // handle transfer payment
                if ($request->payment_method == 'TRANSFER') {
                    $payment->transaction_reference = $request->transfer_reference;
                    $payment->bank_transfer_to = $request->bank_transfer_to;
                }

                $existingHistory = is_array($payment->history)
                    ? $payment->history
                    : json_decode($payment->history ?? '[]', true);

                $payment->history = array_merge($existingHistory, [
                    ['date' => now(), 'title' => PaymentStatus::COMPLETED],
                    ['date' => now(), 'title' => 'CONFIRMED', 'confirmed_by' => "$staff->name ($staff->staff_number)"],
                ]);

                $payment->status = PaymentStatus::COMPLETED;
                $payment->payment_method = $request->payment_method;
                $payment->lastUpdatedBy()->associate($staff);
                $payment->confirmedBy()->associate($staff);
                $payment->remark = $request->remark;
                $payment->save();

                return response()->json([
                    'message' => 'Payment marked as paid successfully',
                    'status' => 'success',
                    'success' => true,
                ]);
            });
        } catch (Exception $th) {
            Log::info($th->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function updateAmount(Request $request, string $id)
    {
        $request->validate([
            'amount' => [
                'required',
                'numeric',
                'min:0'
            ]
        ]);

        try {
            $staff = Auth::user();

            $hmoPayment = Payment::with('patient.wallet', 'lastUpdatedBy')
                ->find($id);

            if (!$hmoPayment) {
                return response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($hmoPayment->payment_method != 'HMO') {
                return response()->json([
                    'message' => 'Only HMO payments can be updated.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($hmoPayment->status == 'COMPLETED') {
                return response()->json([
                    'message' => 'You cannot update a completed payment.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $requestedHmoAmount = (float) $request->amount;
            $totalAmount = (float) $hmoPayment->amount;

            if ($requestedHmoAmount > $totalAmount) {
                return response()->json([
                    'message' => 'Requested amount cannot exceed the total amount for the service.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            // Update the HMO payment portion
            $hmoPayment->amount_payable = number_format($requestedHmoAmount, 2, '.', '');
            $hmoPayment->last_updated_by_id = $staff->id;
            $hmoPayment->save();

            // Calculate expected patient portion
            $expectedPatientBalance = $totalAmount - $requestedHmoAmount;

            // Get existing child payments
            $childPayments = Payment::where('parent_id', $hmoPayment->id)->get();
            $totalAlreadyAccountedFor = $childPayments->sum(function ($p) {
                return (float) $p->amount_payable;
            });

            if ($totalAlreadyAccountedFor > $expectedPatientBalance) {
                return response()->json([
                    'message' => 'Patient has already paid more than the expected balance. Please review payments manually.',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $newBalanceToBePaid = $expectedPatientBalance - $totalAlreadyAccountedFor;

            if ($newBalanceToBePaid > 0) {
                Payment::create([
                    'amount' => number_format($newBalanceToBePaid, 2, '.', ''),
                    'amount_payable' => number_format($newBalanceToBePaid, 2, '.', ''),
                    'transaction_reference' => strtoupper(Str::random(10)),
                    'type' => $hmoPayment->type,
                    'status' => 'CREATED',
                    'customer_name' => $hmoPayment->customer_name,
                    'payable_id' => $hmoPayment->payable_id,
                    'payable_type' => $hmoPayment->payable_type,
                    'patient_id' => $hmoPayment->patient_id,
                    'parent_id' => $hmoPayment->id,
                    'added_by_id' => $staff->id,
                    'last_updated_by_id' => $staff->id,
                ]);
            }

            return response()->json([
                'message' => 'Payment amount updated successfully.',
                'status' => 'success',
                'success' => true,
            ]);
        } catch (\Throwable $th) {
            Log::error('Payment update failed: ' . $th->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function markAsUnPaid($id)
    {
        try {
            $staff = Auth::user();
            $payment = Payment::with(['patient.wallet', 'lastUpdatedBy'])->find($id);
            if (!$payment) {
                return response()->json([
                    'message' => 'The payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            if ($payment->status != PaymentStatus::COMPLETED->value) {
                return response()->json([
                    'message' => 'The payment is not yet confirmed',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $saleRecord = ProductSales::with(['payment', 'salesItems.product'])
                ->whereHas('payment', fn($q) => $q->where('id', $id))
                ->first();

            if ($saleRecord) {
                foreach ($saleRecord->salesItems as $item) {
                    if ($item->product) {
                        $item->product->quantity_sold -= $item->quantity_sold;
                        $item->product->quantity_available_for_sales += $item->quantity_sold;
                        $item->product->save();
                    }
                }
            }

            $payment->status = PaymentStatus::PENDING;
            $existingHistory = is_array($payment->history) ? $payment->history : json_decode($payment->history ?? '[]', true);
            $payment->history = array_merge($existingHistory, [
                ['date' => now(), 'title' => PaymentStatus::PENDING->value],
                ['date' => now(), 'title' => 'UNCONFIRMED', "unconfirmed_by" => "$staff->name ($staff->staff_number)"],
            ]);
            $payment->last_updated_by_id = $staff->id;
            $payment->confirmed_by_id = null;
            $payment->save();

            if ($payment->payment_method == 'WALLET') {
                $payment->patient->wallet->deposit_balance = ((float) $payment->patient->wallet->deposit_balance + (float) $payment->amount);
                $payment->patient->wallet->save();
            }

            return response()->json([
                'message' => 'Payment marked as unpaid successfully',
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

    public function delete($id)
    {
        try {
            $payment = Payment::with(['patient.wallet', 'lastUpdatedBy'])->find($id);
            if (!$payment) {
                return response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $payment->delete();

            return response()->json([
                'message' => 'Payment marked as unpaid successfully',
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

    public function deleteHmoPayment($id)
    {
        Log::info("Delete");
        try {
            $payment = OrganisationAndHmoPayment::find($id);
            if (!$payment) {
                return response()->json([
                    'message' => 'Payment detail not found',
                    'status' => 'error',
                    'success' => false,
                ], 400);
            }

            $payment->delete();

            return response()->json([
                'message' => 'Payment delete successfully',
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

    public function addHMOPayment(Request $request)
    {

        $request->validate([
            'hmo_id' => 'required|integer|exists:organisation_and_hmos,id',
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|in:CASH,WALLET,TRANSFER,HMO',
            'reference' => 'nullable|string',
        ], [
            'hmo_id.required' => 'Organisation detail is required',
            'amount_paid.required' => 'Amount Paid is required',
            'payment_date.required' => 'Payment Date is required',
            'payment_method.required' => 'Payment Method is required',
        ]);

        try {
            $staff = Auth::user();
            $hmo = OrganisationAndHmo::find($request->hmo_id);
            if (!$hmo) {
                return response()->json([
                    'message' => 'HMO not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            $balanceInfo = $this->calculateOutstandingBalance(
                $request->hmo_id,
                null,
                (float) $request->amount_paid
            );

            $payment = new OrganisationAndHmoPayment();
            $payment->amount_paid = $request->amount_paid;
            $payment->hmo_id = $hmo->id;
            $payment->outstanding_balance = $balanceInfo['outstandingBalance'];
            $payment->payment_date = $request->payment_date;
            $payment->total_due = $balanceInfo['totalDue'];
            $payment->payment_method = $request->payment_method;
            $payment->transaction_reference = strtoupper(Str::random(10));
            $payment->reference = $request->reference;
            $payment->added_by_id = $staff->id;
            $payment->last_updated_by_id = $staff->id;
            $payment->history = [
                [
                    'date' => now(),
                    'action' => 'ADD',
                    'performed_by' => $staff->id,
                ]
            ];
            $payment->save();

            return response()->json([
                'message' => 'Payment recorded successfully',
                'outstandingBalance' => $balanceInfo['outstandingBalance'],
                'status' => 'success',
                'success' => true
            ]);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    public function updateHMOPayment(string $id, Request $request)
    {

        $request->validate([
            'hmo_id' => 'required|integer|exists:organisation_and_hmos,id',
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|string|in:CASH,WALLET,TRANSFER,HMO',
            'reference' => 'nullable|string',
        ], [
            'hmo_id.required' => 'Organisation detail is required',
            'amount_paid.required' => 'Amount Paid is required',
            'payment_date.required' => 'Payment Date is required',
            'payment_method.required' => 'Payment Method is required',
        ]);

        try {
            $staff = Auth::user();
            $payment = OrganisationAndHmoPayment::with('hmo')->find($id);

            if (!$payment) {
                return response()->json([
                    'message' => 'HMO Payment record not found',
                    'success' => false,
                    'status' => 'error',
                ], 400);
            }

            $balanceInfo = $this->calculateOutstandingBalance(
                $request->hmo_id,
                (float) $payment->amount_paid,
                (float) $request->amount_paid
            );

            $payment->total_due = $balanceInfo['totalDue'];
            $payment->amount_paid = $request->amount_paid;
            $payment->outstanding_balance = $balanceInfo['outstandingBalance'];
            $payment->payment_date = $request->payment_date;
            $payment->last_updated_by_id = $staff->id;
            $payment->reference = $request->reference;
            $payment->payment_method = $request->payment_method;
            $history = $payment->history ?? [];
            $history[] = [
                'date' => now(),
                'action' => 'UPDATE',
                'performed_by' => $staff->id,
            ];
            $payment->history = $history;
            $payment->save();

            return response()->json([
                'message' => 'Payment record updated successfully',
                'status' => 'success',
                'success' => true
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again in 5 minutes',
                'status' => 'error',
                'success' => false
            ], 500);
        }
    }

    // private function calculateOutstandingBalance(string $hmoId, ?float $previousAmountPaid, float $newAmountPaid): array
    // {
    //     try {
    //         $totalDue = Payment::whereHas('patient.organisationHmo', function ($query) use ($hmoId) {
    //             $query->where('id', $hmoId);
    //         })->where("status", PaymentStatus::COMPLETED->value)->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));

    //         // Total amount paid so far by this HMO
    //         $totalPaidBeforeUpdate = OrganisationAndHmoPayment::where('hmo_id', $hmoId)
    //             ->sum(DB::raw('CAST(amount_paid AS DECIMAL(12,2))'));

    //         // Handle nulls gracefully
    //         $totalDue = $totalDue ?? 0;
    //         $totalPaidBeforeUpdate = $totalPaidBeforeUpdate ?? 0;

    //         // Current outstanding balance
    //         $currentOutstandingBalance = $totalDue - $totalPaidBeforeUpdate;

    //         // New outstanding balance (e.g. when updating an existing record)
    //         $newOutstandingBalance = $currentOutstandingBalance + (($previousAmountPaid ?? 0) - $newAmountPaid);

    //         return [
    //             'outstandingBalance' => $newOutstandingBalance,
    //             'totalDue' => $totalDue,
    //         ];
    //     } catch (Exception $e) {
    //         throw new Exception("Failed to calculate outstanding balance: " . $e->getMessage(), 500);
    //     }
    // }


    private function calculateOutstandingBalance(string $hmoId, ?float $previousAmountPaid, float $newAmountPaid): array
    {
        try {
            // Total amount due from payments for this HMO
            $totalDue = Payment::where('hmo_id', $hmoId)
                ->where('status', PaymentStatus::COMPLETED->value)
                ->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));

            // Total amount paid so far by this HMO
            $totalPaidBeforeUpdate = OrganisationAndHmoPayment::where('hmo_id', $hmoId)
                ->sum(DB::raw('CAST(amount_paid AS DECIMAL(12,2))'));

            // Ensure numeric values (no nulls)
            $totalDue = (float) $totalDue;
            $totalPaidBeforeUpdate = (float) $totalPaidBeforeUpdate;

            // Current outstanding balance
            $currentOutstandingBalance = $totalDue - $totalPaidBeforeUpdate;

            // Adjust outstanding balance for update scenario
            $newOutstandingBalance = $currentOutstandingBalance + (($previousAmountPaid ?? 0) - $newAmountPaid);

            return [
                'outstandingBalance' => round($newOutstandingBalance, 2),
                'totalDue' => round($totalDue, 2),
            ];
        } catch (Exception $e) {
            throw new Exception("Failed to calculate outstanding balance: " . $e->getMessage(), 500);
        }
    }


    // private function calculateOutstandingBalanceBatch(array $hmoIds): array
    // {
    //     if (empty($hmoIds)) return [];

    //     $totalDuePerHmo = DB::table('payments')
    //         ->join('patients', 'patients.id', '=', 'payments.patient_id')
    //         ->whereIn('patients.organisation_hmo_id', $hmoIds)
    //         ->where('payments.status', PaymentStatus::COMPLETED->value)
    //         ->selectRaw('patients.organisation_hmo_id as hmo_id, SUM(CAST(payments.amount AS DECIMAL(12,2))) as total_due')
    //         ->groupBy('patients.organisation_hmo_id')
    //         ->pluck('total_due', 'hmo_id');

    //     $totalPaidPerHmo = DB::table('organisation_and_hmo_payments')
    //         ->whereIn('hmo_id', $hmoIds)
    //         ->selectRaw('hmo_id, SUM(CAST(amount_paid AS DECIMAL(12,2))) as total_paid')
    //         ->groupBy('hmo_id')
    //         ->pluck('total_paid', 'hmo_id');

    //     $result = [];
    //     foreach ($hmoIds as $hmoId) {
    //         $due = (float) ($totalDuePerHmo[$hmoId] ?? 0);
    //         $paid = (float) ($totalPaidPerHmo[$hmoId] ?? 0);
    //         $result[$hmoId] = [
    //             'totalDue' => round($due, 2),
    //             'totalPaid' => round($paid, 2),
    //             'outstandingBalance' => round($due - $paid, 2),
    //         ];
    //     }

    //     return $result;
    // }

    private function calculateOutstandingBalanceBatch(array $hmoIds): array
    {
        if (empty($hmoIds)) {
            return [];
        }

        // Total due per HMO (from confirmed/completed payments)
        $totalDuePerHmo = DB::table('payments')
            ->whereIn('hmo_id', $hmoIds)
            ->where('status', PaymentStatus::COMPLETED->value)
            ->selectRaw('hmo_id, SUM(CAST(amount AS DECIMAL(12,2))) as total_due')
            ->groupBy('hmo_id')
            ->pluck('total_due', 'hmo_id');

        // Total paid per HMO (from settlement records)
        $totalPaidPerHmo = DB::table('organisation_and_hmo_payments')
            ->whereIn('hmo_id', $hmoIds)
            ->selectRaw('hmo_id, SUM(CAST(amount_paid AS DECIMAL(12,2))) as total_paid')
            ->groupBy('hmo_id')
            ->pluck('total_paid', 'hmo_id');

        // Combine results
        $result = [];
        foreach ($hmoIds as $hmoId) {
            $due = (float) ($totalDuePerHmo[$hmoId] ?? 0);
            $paid = (float) ($totalPaidPerHmo[$hmoId] ?? 0);

            $result[$hmoId] = [
                'totalDue' => round($due, 2),
                'totalPaid' => round($paid, 2),
                'outstandingBalance' => round($due - $paid, 2),
            ];
        }

        return $result;
    }


    private function generateUniqueTransactionReference(): string
    {
        do {
            $ref = strtoupper(Str::random(10));
        } while (Payment::where('transaction_reference', $ref)->exists());

        return $ref;
    }
}
