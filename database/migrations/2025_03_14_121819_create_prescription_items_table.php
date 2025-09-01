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
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->string('dosage')->nullable();
            $table->enum('dosage_unit', ['mg', 'ml', 'tablet', 'capsule'])->nullable();
            $table->enum('status', ['CREATED', 'DISPENSED', 'DECLINED'])->default('CREATED');
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->longText('instructions')->nullable();
            $table->unsignedBigInteger('product_id')->constrained('products')->onDelete('cascade');
            $table->unsignedBigInteger('prescription_id')->constrained('prescriptions')->onDelete('cascade');
            $table->unsignedBigInteger('dispensed_by_id')->constrained('users')->onDelete('cascade');
            $table->json('history')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescription_items');
    }
};
