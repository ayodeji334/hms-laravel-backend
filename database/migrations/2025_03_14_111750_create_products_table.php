<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('generic_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_enable_discount_price')->default(false);
            $table->string('sales_price')->nullable();
            $table->string('unit_price')->nullable();
            $table->string('purchase_price')->nullable();
            $table->string('batch_code')->nullable();
            $table->string('storage_condition')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_expired')->default(false);
            $table->boolean('is_out_of_stock')->default(false);
            $table->enum('status', ['AVAILABLE', 'EXPIRED', 'DAMAGED', 'OUT-OF-STOCK'])->default('AVAILABLE');
            $table->integer('quantity_purchase')->default(0);
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('quantity_available_for_sales')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('number_of_cartons')->default(0);
            $table->integer('number_of_packs')->default(0);
            $table->integer('minimum_number_before_reorder')->default(1);
            $table->boolean('is_prescription_required')->nullable();
            $table->enum('dosage_type', ['TABLET', 'SYRUP', 'CAPSULE', 'INJECTION', 'CREAM', 'OINTMENT', 'OTHER'])->nullable();
            $table->string('dosage_strength')->nullable();
            $table->string('nafdac_code')->nullable();
            $table->string('weight')->nullable();
            $table->string('dimension')->nullable();
            $table->datetime('last_updated_on')->nullable();
            $table->datetime('stock_updated_on')->nullable();
            $table->foreignId('stock_last_updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('product_type_id')->nullable()->constrained('product_types')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('product_manufacturers')->nullOnDelete();
            $table->date('expiry_date')->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
