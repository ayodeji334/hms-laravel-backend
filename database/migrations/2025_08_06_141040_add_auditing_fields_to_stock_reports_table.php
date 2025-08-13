<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->nullable()->after('last_updated_by_id');
            $table->decimal('sales_price', 10, 2)->nullable()->after('unit_price');
            $table->unsignedInteger('quantity_before')->nullable()->after('sales_price');
            $table->unsignedInteger('quantity_after')->nullable()->after('quantity_before');
            $table->unsignedInteger('quantity_purchased')->nullable()->after('quantity_after');
            $table->unsignedInteger('quantity_sold')->nullable()->after('quantity_purchased');
        });
    }

    public function down(): void
    {
        Schema::table('stock_reports', function (Blueprint $table) {
            $table->dropColumn([
                'unit_price',
                'sales_price',
                'quantity_before',
                'quantity_after',
                'quantity_purchased',
                'quantity_sold',
            ]);
        });
    }
};
