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
        Schema::create('lab_test_template_table_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('lab_test_template_tables')->onDelete('cascade');
            $table->string('header')->nullable();
            $table->unsignedInteger('index')->nullable();
            $table->json('sub_columns')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_template_table_columns');
    }
};
