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
        Schema::create('ante_natal_routine_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('gestational_age')->nullable();
            $table->string('risk')->nullable();
            $table->longText('comment')->nullable();
            $table->date('date')->nullable();
            $table->string('height_of_fundus')->nullable();
            $table->string('presentation_and_position')->nullable();
            $table->string('presenting_part_to_brim')->nullable();
            $table->string('foetal_heart')->nullable();
            $table->string('urine')->nullable();
            $table->string('blood_pressure')->nullable();
            $table->string('weight')->nullable();
            $table->string('pcv')->nullable();
            $table->string('oedemia')->nullable();
            $table->longText('remarks')->nullable();
            $table->foreignId('examiner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ante_natal_id')->nullable()->constrained('ante_natals')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ante_natal_routine_assessments');
    }
};
