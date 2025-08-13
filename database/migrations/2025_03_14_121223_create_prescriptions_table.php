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
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['CREATED', 'NOT-DISPENSE', 'DISPENSED'])->default('CREATED');
            $table->datetime('last_declined_on')->nullable();
            $table->datetime('last_approved_on')->nullable();
            $table->json('history_logs')->nullable();
            $table->unsignedBigInteger('treatment_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('ante_natal_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('sales_record_id')->nullable()->constrained()->onDelete('set null');
            $table->unsignedBigInteger('requested_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('last_approved_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('last_declined_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->unsignedBigInteger('patient_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('visitation_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescriptions');
    }
};
