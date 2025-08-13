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
        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->onDelete('cascade');
            $table->foreignId('admission_id')->nullable()->constrained('admissions')->onDelete('set null');
            $table->foreignId('visitation_id')->nullable()->constrained('visitations')->onDelete('set null');
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('treated_by_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->longText('diagnosis')->nullable();
            $table->date('treatment_date')->nullable();
            $table->date('treatment_end_date')->nullable();
            $table->string('treatment_type')->nullable();
            $table->enum('status', ['IN_PROGRESS', 'COMPLETED', 'CANCELLED'])->default('IN_PROGRESS');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treatments');
    }
};
