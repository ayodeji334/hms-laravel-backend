<?php

namespace App\Jobs;

use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessProductStatusCheck implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $now = Carbon::now();

        // Update expired products
        $expiredCount = Product::where('status', 'AVAILABLE')
            ->whereDate('expiry_date', '<', $now)
            ->update([
                'status' => 'EXPIRED',
                'is_available' => false,
            ]);

        // Update out-of-stock products
        $outOfStockCount = Product::where('status', 'AVAILABLE')
            ->whereColumn('stock_alert_threshold', '>', 'quantity_available_for_sales')
            ->update([
                'status' => 'OUT_OF_STOCK',
                'is_available' => false,
            ]);

        Log::info('Product status update job ran', [
            'expired_updated' => $expiredCount,
            'out_of_stock_updated' => $outOfStockCount,
        ]);
    }
}
