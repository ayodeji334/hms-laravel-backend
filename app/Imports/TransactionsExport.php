<?php

namespace App\Exports;

use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromView;
use Illuminate\Contracts\View\View;

class TransactionsExport implements FromView
{
    protected $from;
    protected $to;
    protected $account;

    public function __construct($from, $to, $account = null)
    {
        $this->from = $from;
        $this->to = $to;
        $this->account = $account;
    }

    public function view(): View
    {
        $transactions = Payment::with(['organisation', 'addedBy', 'confirmedBy'])
            ->whereBetween('created_at', [$this->from, $this->to])
            ->orderBy('created_at', 'asc')
            ->get();

        // --- Categorization ---
        $successful = $transactions->where('status', 'COMPLETED');
        $failed     = $transactions->where('status', 'FAILED');
        $others     = $transactions->reject(fn($t) => in_array($t->status, ['COMPLETED', 'FAILED']));

        // --- Summaries ---
        $summary = [
            'successful_total' => $successful->sum('amount'),
            'failed_total'     => $failed->sum('amount'),
            'others_total'     => $others->sum('amount'),
        ];

        return view('reports.transaction-report', [
            'transactions'  => $transactions,
            'summary'       => $summary,
            'successful'    => $successful,
            'failed'        => $failed,
            'others'        => $others,
            'period'        => ['from' => $this->from, 'to' => $this->to],
            'date_printed'  => now()->format('Y-m-d H:i:s'),
            'account'       => $this->account,
        ]);
    }
}
