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
        Schema::create('general_examinations', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_admitted_before')->nullable();
            $table->string('is_admitted_before_remark')->nullable();
            $table->boolean('is_undergo_surgical_operation_before')->nullable();
            $table->string('is_undergo_surgical_operation_before_remark')->nullable();
            $table->boolean('is_presently_on_medication_or_treatment')->nullable();
            $table->string('is_presently_on_medication_or_treatment_remark')->nullable();
            $table->string('is_suffer_mental_illness_before')->nullable();
            $table->string('is_suffer_mental_illness_before_remark')->nullable();
            $table->boolean('is_suffer_asthma_or_breathlessness_before')->nullable();
            $table->string('is_suffer_asthma_or_breathlessness_before_remark')->nullable();
            $table->boolean('is_suffer_deafness_or_ear_discharge_before')->nullable();
            $table->string('is_suffer_deafness_or_ear_discharge_before_remark')->nullable();
            $table->boolean('is_suffer_sleep_disturbance_before')->nullable();
            $table->string('is_suffer_sleep_disturbance_before_remark')->nullable();
            $table->boolean('is_suffer_abnormal_bleeding_before')->nullable();
            $table->string('is_suffer_abnormal_bleeding_before_remark')->nullable();
            $table->boolean('is_suffer_fainting_attacks_or_griddiness_before')->nullable();
            $table->string('is_suffer_fainting_attacks_or_griddiness_before_remark')->nullable();
            $table->boolean('is_suffer_epilepsy_or_fits_before')->nullable();
            $table->string('is_suffer_epilepsy_or_fits_before_remark')->nullable();
            $table->boolean('is_suffer_recurrent_headaches_or_migraine_before')->nullable();
            $table->string('is_suffer_recurrent_headaches_or_migraine_before_remark')->nullable();
            $table->boolean('is_suffer_diabetes_mellitus_before')->nullable();
            $table->string('is_suffer_diabetes_mellitus_before_remark')->nullable();
            $table->boolean('is_suffer_jaundice_before')->nullable();
            $table->string('is_suffer_jaundice_before_remark')->nullable();
            $table->boolean('is_suffer_sickle_cells_disease_before')->nullable();
            $table->string('is_suffer_sickle_cells_disease_before_remark')->nullable();
            $table->boolean('is_suffer_skin_disorder_before')->nullable();
            $table->string('is_suffer_skin_disorder_before_remark')->nullable();
            $table->boolean('is_suffer_recurrent_indigestion_before')->nullable();
            $table->string('is_suffer_recurrent_indigestion_before_remark')->nullable();
            $table->boolean('is_suffer_tuberculosis_before')->nullable();
            $table->string('is_suffer_tuberculosis_before_remark')->nullable();
            $table->boolean('is_suffer_congenital_deformity_before')->nullable();
            $table->string('is_suffer_congenital_deformity_before_remark')->nullable();
            $table->boolean('is_suffer_foot_knee_back_neck_trouble_before')->nullable();
            $table->string('is_suffer_foot_knee_back_neck_trouble_before_remark')->nullable();
            $table->boolean('is_suffer_allergy_before')->nullable();
            $table->string('is_suffer_allergy_before_remark')->nullable();
            $table->json('allergies')->nullable();
            $table->json('family_sickness_history')->nullable();
            $table->json('immunized_against_diseases')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->cascadeOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_examinations');
    }
};
