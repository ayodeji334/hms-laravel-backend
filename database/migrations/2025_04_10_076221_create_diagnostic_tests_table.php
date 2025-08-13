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
        Schema::create('diagnostic_tests', function (Blueprint $table) {
            $table->id();
            $table->json('result_details')->nullable();
            $table->date('result_date')->nullable();
            $table->boolean('is_save_as_draft')->nullable();
            $table->unsignedBigInteger('result_carried_out_by_id');
            $table->unsignedBigInteger('test_id');
            $table->unsignedBigInteger('request_id');
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('patient_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_tests');
    }
};
