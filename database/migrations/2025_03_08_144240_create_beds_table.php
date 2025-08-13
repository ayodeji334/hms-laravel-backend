<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('status', ['AVAILABLE', 'OCCUPIED', 'DAMAGED'])->default('AVAILABLE');
            $table->foreignId('assigned_patient_id')->nullable()->constrained('patients')->onDelete('set null');
            $table->foreignId('room_id')->constrained('rooms')->onDelete('cascade');
            $table->foreignId('created_by_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('last_deleted_by_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beds');
    }
};
