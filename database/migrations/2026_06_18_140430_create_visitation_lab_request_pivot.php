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
        Schema::create('visitation_lab_requests', function (Blueprint $table) {
            $table->id();

            // Link to visitation
            $table->foreignId('visitation_id')
                ->constrained('visitations')
                ->onDelete('cascade');

            // Link to lab_requests instead of services
            $table->foreignId('lab_request_id')
                ->constrained('lab_requests')
                ->onDelete('cascade');

            // Removed the unique constraint to allow duplicates
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitation_lab_requests');
    }
};
