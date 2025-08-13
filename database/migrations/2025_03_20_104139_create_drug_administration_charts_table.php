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
        Schema::create('drug_administration_charts', function (Blueprint $table) {
            $table->id();
            $table->longText('procedure')->nullable();
            $table->time('time')->nullable();
            $table->date('date')->nullable();
            $table->string('dosage')->nullable();
            $table->string('day')->nullable();
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
        Schema::dropIfExists('drug_administration_charts');
    }
};
