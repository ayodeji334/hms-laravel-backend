<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('diagnostic_tests', function (Blueprint $table) {
            // Rename request_id to requestable_id
            $table->renameColumn('request_id', 'requestable_id');

            // Add requestable_type for polymorphic relation
            $table->string('requestable_type')->nullable()->after('requestable_id');
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_tests', function (Blueprint $table) {
            // Rollback changes
            $table->renameColumn('requestable_id', 'request_id');
            $table->dropColumn('requestable_type');
        });
    }
};
