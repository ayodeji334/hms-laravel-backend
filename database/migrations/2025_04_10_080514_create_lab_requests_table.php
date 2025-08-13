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
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_approval_required')->nullable();
            $table->enum('priority', ['ROUTINE', 'URGENT'])->nullable()->default('ROUTINE');
            $table->boolean('is_patient')->nullable();
            $table->dateTime('sample_collected_date')->nullable();
            $table->boolean('require_sample_collection')->nullable();
            $table->string('sample_label')->nullable();
            $table->string('sample_type')->nullable();
            $table->date('request_date')->nullable();
            $table->date('approved_date')->nullable();
            $table->unsignedBigInteger('service_id');
            $table->string('customer_name')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('treatment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('test_result_id')->nullable()->constrained('diagnostic_tests')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_requests');
    }
};
