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
        Schema::create('organisation_and_hmo_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmo_id')->nullable()->constrained('organisation_and_hmos')->onDelete('set null');
            $table->foreignId('added_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('total_due')->default('0');
            $table->string('amount_paid')->default('0');
            $table->string('outstanding_balance')->default('0');
            $table->string('transaction_reference');
            $table->date('payment_date');
            $table->json('history')->nullable();
            $table->enum('payment_method', ['CASH', 'TRANSFER'])->default('TRANSFER');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_and_hmo_payments');
    }
};
