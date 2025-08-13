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
        Schema::create('product_purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained('product_purchases')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained("products")->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('product_manufacturers')->nullOnDelete();
            $table->string('purchase_price')->nullable();
            $table->integer('number_of_cartons')->default(0);
            $table->integer('number_of_packs')->default(0);
            $table->integer('total_quantity')->default(0);
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_purchase_items');
    }
};
