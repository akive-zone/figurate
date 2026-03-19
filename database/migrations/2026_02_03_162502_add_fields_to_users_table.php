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
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('person')->after('password');
            $table->string('provider')->nullable()->after('type');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('status')->default('active')->after('provider_id');
            $table->string('device_identifier')->nullable()->after('status');

            $table->index(['type', 'status']);
            $table->unique(['device_identifier']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['device_identifier']);
            $table->dropIndex(['type', 'status']);
            $table->dropColumn(['type', 'provider', 'provider_id', 'status', 'device_identifier']);
        });
    }
};
