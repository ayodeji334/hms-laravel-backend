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
        Schema::create('operation_record_assistant_surgeons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_record_id')->constrained()->onDelete('cascade');
            $table->foreignId('assistant_surgeon_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_record_assistant_surgeons');
    }
};
