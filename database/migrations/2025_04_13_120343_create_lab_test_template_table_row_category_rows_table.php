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
        Schema::create('lab_test_template_table_row_category_rows', function (Blueprint $table) {
            $table->id();
            $table->json('values');
            $table->unsignedInteger('index')->nullable();
            $table->foreignId('category_id')->constrained('lab_test_template_table_row_categories')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_test_template_table_row_category_rows');
    }
};
