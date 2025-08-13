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
        Schema::create('hmo_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hmo_id')->constrained('organisation_and_hmos')->onDelete('cascade');
            $table->string('total_due')->default('0')->nullable();
            $table->string('amount_paid')->default('0')->nullable();
            $table->string('outstanding_balance')->default('0')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->date('payment_date')->nullable();
            $table->json('history')->nullable();
            $table->enum('payment_method', ['TRANSFER', 'CASH', 'ORGANISATION', 'HMO', 'WALLET'])->default('TRANSFER');
            $table->foreignId('added_by_id')->nullable()->constrained('users')->onDelete('set null');
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
        Schema::dropIfExists('hmo_payments');
    }
};
