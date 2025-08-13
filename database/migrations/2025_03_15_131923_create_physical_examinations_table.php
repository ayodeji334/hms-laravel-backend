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
        Schema::create('physical_examinations', function (Blueprint $table) {
            $table->id();
            // Boolean fields with remarks
            $columns = ['deformities', 'pallor', 'jaundice', 'cyanosis', 'oedema', 'skin_disease'];
            foreach ($columns as $column) {
                $table->boolean("is_suffer_{$column}_before")->nullable();
                $table->longText("is_suffer_{$column}_before_remark")->nullable();
            }

            // Vision
            $table->string('right_eye_vision_acuity_without_glasses')->nullable();
            $table->string('left_eye_vision_acuity_without_glasses')->nullable();
            $table->string('right_eye_vision_acuity_with_glasses')->nullable();
            $table->string('left_eye_vision_acuity_with_glasses')->nullable();
            $table->enum('color_vision_test', ['NORMAL', 'DEFICIENT'])->nullable();

            // General Information
            $table->string('height')->nullable();
            $table->string('weight')->nullable();
            $table->string('bmi')->nullable();

            // Cardiovascular & Respiratory
            $table->string('apex_beat')->nullable();
            $table->string('heart_sound')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('pulse')->nullable();

            // Inspection, Palpation, Percussion, Auscultation sections
            $sections = ['respiratory', 'abdominal', 'rectal', 'genital', 'breast'];
            foreach ($sections as $section) {
                $table->longText("{$section}_inspection")->nullable();
                $table->longText("{$section}_palpation")->nullable();
                $table->longText("{$section}_percussion")->nullable();
                $table->longText("{$section}_auscultation")->nullable();
            }
            $table->longText('mental_altertness')->nullable();
            $table->string('glasgow_coma_scale')->nullable();
            $table->longText('other_examination')->nullable();
            $table->enum('recommendation_status', ['PENDING', 'APPROVED', 'REJECTED'])->nullable();
            $table->foreignId('visitation_id')->constrained('visitations')->onDelete('cascade');
            $table->foreignId('added_by_id')->constrained('users')->onDelete('cascade');
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
        Schema::dropIfExists('physical_examinations');
    }
};
