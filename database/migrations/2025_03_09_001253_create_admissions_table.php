<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->date('admission_date')->nullable();
            $table->dateTime('discharge_date')->nullable();
            $table->longText('patient_insurance_detail')->nullable();
            $table->enum('type', ['ELECTIVE', 'EMERGENCY', 'URGENT'])->default('ELECTIVE');
            $table->enum('status', ['ADMITTED', 'DISCHARGED', 'TRANSFERRED'])->default('ADMITTED');
            $table->string('fluid_balance_instructions')->nullable();
            $table->json('drug_charts_detail')->nullable();
            $table->json('temperature_charts')->nullable();
            $table->foreignId('admitted_by_id')->nullable()->constrained('users');
            $table->foreignId('discharged_by_id')->nullable()->constrained('users');
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('added_by_id')->nullable()->constrained('users');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users');
            $table->foreignId('last_deleted_by_id')->nullable()->constrained('users');
            $table->foreignId('bed_id')->nullable()->constrained('beds');
            $table->longText('notes')->nullable();
            $table->longText('diagnosis')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
