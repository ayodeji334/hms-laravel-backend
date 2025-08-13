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
        Schema::create('labour_summaries', function (Blueprint $table) {
            $table->id();
            $table->longText('induction')->nullable();
            $table->longText('indication')->nullable();
            $table->string('method_of_delivery')->nullable();
            $table->date('expected_date_delivery')->nullable();
            $table->longText('cephalic_presentation')->nullable();
            $table->longText('breech_presentation')->nullable();
            $table->string('placenta_membranes')->nullable();
            $table->string('perineum')->nullable();
            $table->dateTime('time_date_of_delivery')->nullable();
            $table->integer('number_of_skin_sutures')->nullable();
            $table->float('number_of_blood_loss')->nullable();
            $table->string('malformation')->nullable();
            $table->json('infants_status')->nullable();
            $table->json('infants_sexes')->nullable();
            $table->json('infants_weights')->nullable();
            $table->string('mother_uterus_condition')->nullable();
            $table->string('mother_bladder_condition')->nullable();
            $table->string('mother_blood_pressure')->nullable();
            $table->string('mother_pulse')->nullable();
            $table->string('mother_temperature')->nullable();
            $table->string('mother_rep')->nullable();
            $table->longText('treatment')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('labour_record_id')->nullable()->constrained('labour_records')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labour_summaries');
    }
};
