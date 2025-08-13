<?php

use App\Enums\VisitationStatus;
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
        Schema::create('visitations', function (Blueprint $table) {
            $table->id();
            $table->date('start_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('title')->nullable();
            $table->longText('description')->nullable();
            $table->json('history');
            $table->enum('status', ['WAITING', 'ACCEPTED', 'UNREFFERED', 'CONSULTED', 'RESCHEDULE'])->default('WAITING');
            $table->enum('type', ['EXAMINATION', 'CONSULTATION', 'SURGERY'])->default('EXAMINATION');
            $table->foreignId('patient_id')->nullable()->constrained('patients');
            $table->foreignId('assigned_doctor_id')->nullable()->constrained('users');
            $table->foreignId('created_by_id')->nullable()->constrained('users');
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users');
            $table->foreignId('last_deleted_by_id')->nullable()->constrained('users');
            $table->text('not_available_tests')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitations');
    }
};
