<?php

use App\Enums\ServiceTypes;
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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('price');
            $table->enum('type', array_map(fn($type) => $type->value, ServiceTypes::cases()))->default(ServiceTypes::LAB_TEST->value);
            $table->boolean('is_available')->default(true);
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('result_template_id')->nullable()->constrained('lab_test_result_templates')->nullOnDelete();
            $table->foreignId('visitation_id')->nullable()->constrained('visitations')->nullOnDelete();
            $table->foreignId('last_updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('service_category_service', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('service_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
