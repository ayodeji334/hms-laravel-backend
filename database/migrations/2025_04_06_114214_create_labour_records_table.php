<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabourRecordsTable extends Migration
{
    public function up(): void
    {
        Schema::create('labour_records', function (Blueprint $table) {
            $table->id();
            $table->string('estimated_gestational_age')->nullable();
            $table->date('expected_date_delivery')->nullable();
            $table->string('last_menstrual_period')->nullable();
            $table->longText('general_condititon')->nullable();
            $table->string('abdomen_fundal_height')->nullable();
            $table->string('abdomen_fundal_lie')->nullable();
            $table->string('abdomen_fundal_position')->nullable();
            $table->string('abdomen_fundal_descent')->nullable();
            $table->string('abdomen_fundal_presentation')->nullable();
            $table->string('foetal_heart_rate')->nullable();
            $table->string('vulva_status')->nullable();
            $table->string('vagina_status')->nullable();
            $table->string('vagina_membranes')->nullable();
            $table->string('cervix_percent')->nullable();
            $table->string('cervix_centimeter')->nullable();
            $table->string('pelvis_sacral_curve')->nullable();
            $table->longText('placenta_pervia_position')->nullable();
            $table->longText('placenta_pervia_current_station')->nullable();
            $table->string('pelvis_conjugate_diameter')->nullable();
            $table->string('pelvis_centimeter')->nullable();
            $table->longText('caput')->nullable();
            $table->longText('moulding')->nullable();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('examiner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('added_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labour_records');
    }
}
