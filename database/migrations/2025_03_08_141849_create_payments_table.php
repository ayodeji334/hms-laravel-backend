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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('amount_payable')->nullable();
            $table->string('refund_amount')->nullable();
            $table->string('amount')->nullable();
            $table->string('transaction_reference')->unique();
            $table->string('reference')->nullable()->unique();
            $table->string('bank_transfer_to')->nullable();
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_used')->default(false);
            $table->enum('payment_method', ['CASH', 'TRANSFER', 'HMO', 'ORGANISATION', 'WALLET'])->nullable();
            $table->string('type')->nullable();
            $table->longText('remark')->nullable();
            $table->enum('status', ['CREATED', 'PENDING', 'COMPLETED', 'FAILED', 'REFUNDED'])->default('CREATED');
            $table->json('history')->nullable();
            $table->string('customer_name');
            $table->nullableMorphs('payable');
            $table->unique(['payable_type', 'payable_id']);
            $table->timestamps();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('added_by_id')->nullable();
            $table->unsignedBigInteger('confirmed_by_id')->nullable();
            $table->unsignedBigInteger('deleted_by_id')->nullable();
            $table->unsignedBigInteger('last_updated_by_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->unsignedBigInteger('hmo_id')->nullable()->after('patient_id');
            $table->foreign('hmo_id')->references('id')->on('hmos')->onDelete('set null');
            $table->unique(['parent_id', 'payment_method']);
            $table->foreign('parent_id')->references('id')->on('payments')->onDelete('cascade');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
