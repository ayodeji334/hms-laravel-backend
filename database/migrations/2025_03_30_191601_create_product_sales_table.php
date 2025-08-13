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
        Schema::create('product_sales', function (Blueprint $table) {
            $table->id();
            $table->json('history')->nullable();
            $table->string('total_price')->nullable();
            $table->string('type')->nullable();
            $table->string('invoice_id')->nullable();
            $table->string('total_discount_price')->nullable();
            $table->string('customer_name')->nullable();
            $table->enum('status', ['CREATED', 'PENDING', 'PAID', 'REFUNDED'])->default('CREATED');
            $table->foreignId('payment_id')->unique()->nullable()->constrained('payments')->onDelete('set null');
            $table->foreignId('patient_id')->nullable()->constrained('patients')->onDelete('set null');
            $table->foreignId('sold_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('confirmed_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sales');
    }
};
