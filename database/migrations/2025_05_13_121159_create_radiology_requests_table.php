<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('radiology_requests', function (Blueprint $table) {
            $table->id();

            $table->longText('clinical_diagnosis')->nullable();
            $table->string('part_examined')->nullable();
            $table->date('result_date')->nullable();
            $table->string('size_of_films')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->integer('number_of_films')->nullable();
            $table->enum('status', ['CREATED', 'CANCELED', 'RESULT_READY'])->default('CREATED')->nullable();
            $table->foreignId('carried_out_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radiology_requests');
    }
};
