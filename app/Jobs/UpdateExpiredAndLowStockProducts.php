<?php

namespace App\Jobs;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class UpdateExpiredAndLowStockProducts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = Carbon::now();

        // Update expired products
        $expiredCount = Product::where('status', 'AVAILABLE')
            ->whereDate('expiry_date', '<', $now)
            ->update([
                'status' => 'EXPIRED',
                'is_expired' => true,
                'is_available' => false,
            ]);

        // Update out-of-stock products
        $outOfStockCount = Product::where('status', 'AVAILABLE')
            ->whereColumn('stock_alert_threshold', '>', 'quantity_available_for_sales')
            ->update([
                'status' => 'OUT-OF-STOCK',
                'is_out_of_stock' => true,
                'is_available' => false,
            ]);

        Log::info('Product status update job ran', [
            'expired_updated' => $expiredCount,
            'out_of_stock_updated' => $outOfStockCount,
        ]);
    }
}
