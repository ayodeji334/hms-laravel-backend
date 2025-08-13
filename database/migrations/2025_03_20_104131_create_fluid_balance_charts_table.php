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
        Schema::create('fluid_balance_charts', function (Blueprint $table) {
            $table->id();
            $table->string('input_type')->nullable();
            $table->integer('input_tube_volume')->nullable();
            $table->integer('input_oral_volume')->nullable();
            $table->integer('input_iv_volume')->nullable();
            $table->integer('input_total')->nullable();
            $table->string('output_type')->nullable();
            $table->integer('output_faeces_volume')->nullable();
            $table->integer('output_urine_volume')->nullable();
            $table->integer('output_vomit_volume')->nullable();
            $table->integer('output_total')->nullable();
            $table->time('time')->nullable();
            $table->longText('instructions')->nullable();
            $table->foreignId('admission_id')->constrained('admissions')->onDelete('cascade');
            $table->foreignId('added_by_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('last_updated_by_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fluid_balance_charts');
    }
};
