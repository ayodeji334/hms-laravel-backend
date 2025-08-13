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
        Schema::create('operation_records', function (Blueprint $table) {
            $table->id();
            $table->longText('diagnosis_before_operation')->nullable();
            $table->longText('post_operation_diagnosis')->nullable();
            $table->longText('procedure_carried_out')->nullable();
            $table->longText('complications')->nullable();
            $table->longText('packs')->nullable();
            $table->longText('specimens')->nullable();
            $table->longText('operative_findings')->nullable();
            $table->string('anesthesia_type')->nullable();
            $table->date('operation_date')->nullable();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('anesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scrub_nurse_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_records');
    }
};
