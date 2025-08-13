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
        Schema::create('organisation_and_hmos', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('phone_number')->unique()->nullable();
            $table->string('contact_address');
            $table->string('email')->unique()->nullable();
            $table->enum('type', ['HMO', 'ORGANISATION'])->nullable();
            $table->string('outstanding_balance')->nullable();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->onDelete('set null');;
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->onDelete('set null');;
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_and_hmos');
    }
};
