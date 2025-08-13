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
        Schema::create('labour_graphs', function (Blueprint $table) {
            $table->id();
            $table->string('maternal_blood_pulse');
            $table->dateTime('time');
            $table->string('maternal_pulse')->nullable();
            $table->string('position')->nullable();
            $table->string('caput')->nullable();
            $table->string('moulding')->nullable();
            $table->string('fetal_heart_rate')->nullable();
            $table->string('cervical_dilation')->nullable();
            $table->string('liquor')->nullable();
            $table->float('maternal_temperature')->nullable();
            $table->json('fluids_and_drugs')->nullable();
            $table->json('uterine_contractions')->nullable();
            $table->json('oxytocin_administrations')->nullable();
            $table->json('urine_analyses')->nullable();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('labour_id')->constrained('labour_records')->onDelete('cascade');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labour_graphs');
    }
};
