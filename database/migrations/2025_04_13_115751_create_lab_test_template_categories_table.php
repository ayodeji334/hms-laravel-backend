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
        Schema::create('lab_test_template_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('index')->nullable();
            $table->string('name')->nullable();
            $table->json('input_fields')->nullable();
            $table->foreignId('template_id')->constrained('lab_test_result_templates')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_template_categories');
    }
};
