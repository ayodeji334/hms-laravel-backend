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
        Schema::create('previous_pregnancies_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('date_of_birth')->nullable();
            $table->string('duration_of_pregnancy')->nullable();
            $table->string('child_weight')->nullable();
            $table->enum('child_gender', ['male', 'female', 'other'])->nullable(); // adjust enum values as needed
            $table->longText('complication_during_pregnancy')->nullable();
            $table->longText('complication_during_labour')->nullable();
            $table->longText('pueperium')->nullable();
            $table->boolean('is_child_still_alive')->nullable();
            $table->longText('cause_of_death')->nullable();
            $table->integer('child_age_before_death')->nullable();
            $table->foreignId('ante_natal_id')->nullable()->constrained('ante_natals')->nullOnDelete();
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
        Schema::dropIfExists('previous_pregnancies_summaries');
    }
};
