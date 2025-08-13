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
        Schema::create('ante_natals', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['NOT_DELIVERED', 'DELIVERED'])->default('NOT_DELIVERED');
            $table->string('duration_of_pregnancy_at_registration')->nullable();
            $table->string('care_id')->nullable();
            $table->integer('age_at_marriage')->nullable();
            $table->date('expected_date_delivery')->nullable();
            $table->date('booking_date')->nullable();
            $table->string('last_menstrual_period')->nullable();
            $table->integer('total_number_of_children')->nullable();
            $table->integer('total_number_of_children_alive')->nullable();
            $table->boolean('has_heart_disease')->nullable();
            $table->boolean('has_undergo_operations')->nullable();
            $table->boolean('has_kidney_disease')->nullable();
            $table->boolean('has_chest_disease')->nullable();
            $table->boolean('has_leprosy_disease')->nullable();
            $table->longText('pregnancy_history')->nullable();
            $table->longText('urinary_symptoms')->nullable();
            $table->longText('bleeding')->nullable();
            $table->longText('vomitting')->nullable();
            $table->longText('discharge')->nullable();
            $table->longText('other_symptoms')->nullable();
            $table->longText('general_condition')->nullable();
            $table->longText('oedema')->nullable();
            $table->longText('anaemia')->nullable();
            $table->longText('respiratory_system')->nullable();
            $table->longText('cardiovascular_system')->nullable();
            $table->longText('abdomen')->nullable();
            $table->longText('spleen')->nullable();
            $table->longText('liver')->nullable();
            $table->longText('preliminary_pelvic_assessment')->nullable();
            $table->longText('other_abnormalities')->nullable();
            $table->string('weight')->nullable();
            $table->longText('ankles_swelling')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('height')->nullable();
            $table->string('urine_albumin')->nullable();
            $table->string('urine_sugar')->nullable();
            $table->string('breast_and_nipples')->nullable();
            $table->string('pcv')->nullable();
            $table->string('genotype')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('vdrl')->nullable();
            $table->string('rh')->nullable();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            // $table->foreignId('registration_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ante_natals');
    }
};
