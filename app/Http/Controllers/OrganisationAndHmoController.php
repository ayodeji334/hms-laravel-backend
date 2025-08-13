<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\HmoPayment;
use App\Models\OrganisationAndHmo;
use App\Models\OrganisationAndHmoPayment;
use App\Models\Payment;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrganisationAndHmoController extends Controller
{
    public function create(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:organisation_and_hmos,name',
            'email' => 'required|email|unique:organisation_and_hmos,email',
            'phone_number' => 'required|string|unique:organisation_and_hmos,phone_number',
            'address' => 'nullable|string',
            'type' => ['required', Rule::in(['HMO', 'ORGANISATION'])],
        ]);

        try {
            $staffId = Auth::user()->id;

            $organisationOrHmo = new OrganisationAndHmo();
            $organisationOrHmo->name = $request->name;
            $organisationOrHmo->added_by_id = $staffId;
            $organisationOrHmo->email = $request->email;
            $organisationOrHmo->type = $request->type;
            $organisationOrHmo->phone_number = $request->phone_number;
            $organisationOrHmo->contact_address = $request->address;
            $organisationOrHmo->save();

            return response()->json([
                'message' => 'Insurance Provider detail created successfully',
                'success' => true,
                'status' => 'success',
            ], 201);
        } catch (Exception $e) {
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organisation_and_hmos', 'name')->ignore($id),
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('organisation_and_hmos', 'email')->ignore($id),
            ],
            'phone_number' => [
                'required',
                'string',
                Rule::unique('organisation_and_hmos', 'phone_number')->ignore($id),
            ],
            'address' => 'nullable|string',
            'type' => ['required', Rule::in(['HMO', 'ORGANISATION'])],
        ]);

        try {
            $organisationOrHmo = OrganisationAndHmo::find($id);

            if (!$organisationOrHmo) {
                return response()->json([
                    'message' => 'Insurance Provider detail not found',
                    'success' => false,
                    'status' => 'error',
                ], 404);
            }

            $organisationOrHmo->name = $request->name;
            $organisationOrHmo->email = $request->email;
            $organisationOrHmo->type = $request->type;
            $organisationOrHmo->phone_number = $request->phone_number;
            $organisationOrHmo->contact_address = $request->address;
            $organisationOrHmo->save();

            return response()->json([
                'message' => 'Insurance Provider detail updated successfully',
                'success' => true,
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());

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
            $searchQuery = $request->get('q', '');
            $type = $request->get('type', '');
            $limit = $request->get('limit', 30);
            $queryBuilder = OrganisationAndHmo::with(['addedBy', 'lastUpdatedBy'])->orderBy('updated_at', 'DESC');

            if (!empty($type)) {
                $queryBuilder->where('type', $type);
            }

            if (!empty($searchQuery)) {
                $queryBuilder->where(function ($qb) use ($searchQuery) {
                    $qb->where('name', 'like', "%$searchQuery%")
                        ->orWhere('email', 'like', "%$searchQuery%")
                        ->orWhere('phone_number', 'like', "%$searchQuery%");
                });
            }

            $data = $queryBuilder->paginate($limit);

            return response()->json([
                'message' => 'Insurance Provider records fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::info("Helllo" . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Try again',
                'success' => false,
                'status' => 'error',
            ], 500);
        }
    }

    public function getOverview($id)
    {
        try {
            // $totalDuePerHmo = Payment::where('status', PaymentStatus::COMPLETED->value)
            //     ->join('patients', 'patients.id', '=', 'payments.patient_id')
            //     ->join('organisation_hmos', 'organisation_hmos.id', '=', 'patients.organisation_hmo_id')
            //     ->where('organisation_hmos.id', $id)
            //     ->sum(DB::raw('CAST(payments.amount AS DECIMAL(12,2))'));
            $totalDuePerHmo = DB::table('payments')
                ->join('patients', 'patients.id', '=', 'payments.patient_id')
                ->join('organisation_hmos', 'organisation_hmos.id', '=', 'patients.organisation_hmo_id')
                ->where('status', PaymentStatus::COMPLETED->value)
                ->where('organisation_hmos.id', $id)
                ->sum(DB::raw('CAST(payments.amount AS DECIMAL(12,2))'));

            Log::info($totalDuePerHmo);

            $totalDue = Payment::with('patient')->where('status', PaymentStatus::COMPLETED->value)
                ->whereHas('patient', function ($patientQuery) use ($id) {
                    $patientQuery->where('organisation_hmo_id', $id);
                })
                ->sum(DB::raw('CAST(amount AS DECIMAL(12,2))'));


            $totalPaid = OrganisationAndHmoPayment::where('hmo_id', $id)
                ->sum(DB::raw('CAST(amount_paid AS DECIMAL(12,2))'));

            // Last payment made by this HMO
            $lastPayment = OrganisationAndHmoPayment::where('hmo_id', $id)
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'message' => 'HMO overview retrieved successfully',
                'status' => 'success',
                'success' => true,
                'data' => [
                    'total_due' => (int) $totalDue ?? 0,
                    'total_paid' => (int) $totalPaid ?? 0,
                    'outstanding_balance' => ($totalDue - $totalPaid),
                    'last_payment_date' => optional($lastPayment)->created_at,
                    'last_amount_paid' => (int) optional($lastPayment)->amount_paid,
                ],
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch HMO overview: ' . $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    public function getTransactions(Request $request, $id)
    {
        try {
            $query = Payment::query()
                ->where('payment_method', 'HMO')
                ->whereHas('patient', function ($q) use ($id) {
                    $q->where('organisation_hmo_id', $id);
                })
                ->with([
                    'patient:id,patient_reg_no,firstname,lastname,insurance_number',
                ]);

            // Optional status filter
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Optional start_date filter
            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            // Optional end_date filter
            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Paginate and sort
            $transactions = $query->orderBy('created_at', 'desc')->paginate();

            return response()->json([
                'message' => 'Transactions fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $transactions,
            ], 200);
        } catch (Exception $e) {
            Log::info($e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch transactions: ' . $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }


    // public function getTransactions(Request $request, $id)
    // {
    //     try {
    //         $query = Payment::query()
    //             ->where('payment_method', 'HMO')
    //             ->where('payable_id', $id);

    //         if ($request->filled('start_date')) {
    //             $query->whereDate('created_at', '>=', $request->start_date);
    //         }

    //         if ($request->filled('end_date')) {
    //             $query->whereDate('created_at', '<=', $request->end_date);
    //         }

    //         $query->with("patient:id,patient_reg_no,firstname,lastname,insurance_number");

    //         $transactions = $query
    //             ->orderBy('created_at', 'desc')
    //             ->paginate();

    //         return response()->json([
    //             'message' => 'Transactions fetched successfully',
    //             'status' => 'success',
    //             'success' => true,
    //             'data' => $transactions,
    //         ], 200);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'message' => 'Failed to fetch transactions: ' . $e->getMessage(),
    //             'status' => 'error',
    //             'success' => false,
    //         ], 500);
    //     }
    // }

    public function getPayments(Request $request, $id)
    {
        try {
            $perPage = $request->input('limit', 50);
            $from = $request->input('from');
            $to = $request->input('to');

            $query = OrganisationAndHmoPayment::with([
                'addedBy:id,firstname,lastname,email',
                'lastUpdatedBy:id,firstname,lastname,email'
            ])
                ->where('hmo_id', $id)
                ->orderBy('created_at', 'desc');

            if ($from && $to) {
                $query->whereBetween('created_at', [
                    Carbon::parse($from)->startOfDay(),
                    Carbon::parse($to)->endOfDay(),
                ]);
            } elseif ($from) {
                $query->whereDate('created_at', '>=', Carbon::parse($from)->startOfDay());
            } elseif ($to) {
                $query->whereDate('created_at', '<=', Carbon::parse($to)->endOfDay());
            }

            $transactions = $query->paginate($perPage);

            return response()->json([
                'message' => 'HMO Payments fetched successfully',
                'status' => 'success',
                'success' => true,
                'data' => $transactions,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch transactions: ' . $e->getMessage(),
                'status' => 'error',
                'success' => false,
            ], 500);
        }
    }

    private function attachOutstandingBalances(array $providers): array
    {
        return array_map(function ($provider) {
            $hmoId = $provider->id;
            $totalTransactions = Payment::join('patients', 'payments.patient_id', '=', 'patients.id')
                ->where('patients.organisation_hmo_id', $hmoId)
                ->sum(DB::raw('CAST(payments.amount AS DECIMAL(12,2))'));

            $totalPayments = HmoPayment::where('hmo_id', $hmoId)
                ->sum(DB::raw('CAST(amount_paid AS DECIMAL(12,2))'));

            $outstanding_balance = $totalTransactions - $totalPayments;
            $provider->totalDue = $totalTransactions;
            $provider->totalPaid = $totalPayments;
            $provider->outstanding_balance = $outstanding_balance;

            return $provider;
        }, $providers);
    }
}
