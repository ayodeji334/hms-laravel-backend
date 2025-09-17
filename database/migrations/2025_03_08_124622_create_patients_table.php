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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('patient_reg_no')->nullable();
            $table->string('email')->nullable();
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('hall_of_residence')->nullable();
            $table->string('contact_address')->nullable();
            $table->string('blood_group')->nullable();
            $table->string('genotype')->nullable();
            $table->string('insurance_number')->nullable();
            $table->string('insurance_provider_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('room_number')->nullable();
            $table->string('permanent_address')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('state_of_origin')->nullable();
            $table->string('lga')->nullable();
            $table->string('religion')->nullable();
            $table->string('next_of_kin_firstname')->nullable();
            $table->string('next_of_kin_lastname')->nullable();
            $table->string('next_of_kin_contact_address')->nullable();
            $table->string('next_of_kin_phone_number')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_debtor')->default(false);
            $table->string('type');
            $table->string('age')->nullable();
            $table->string('matriculation_number')->nullable();
            $table->string('staff_number')->nullable();
            $table->string('gender')->nullable();
            $table->string('occupation')->nullable();
            $table->string('tribe')->nullable();
            $table->string('marital_status')->default('SINGLE');
            $table->string('nationality')->nullable();
            $table->string('department')->nullable();
            $table->string('level')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('last_updated_on')->nullable();
            $table->string('title')->default('DR')->nullable();
            $table->dateTime('password_changed_on')->nullable();
            $table->dateTime('last_loggedin_on')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained("users")->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
