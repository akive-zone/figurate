<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_agents', function (Blueprint $table): void {
            $table->string('device_identifier')->nullable()->after('kind');
            $table->unique('device_identifier', 'user_agents_device_identifier_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_agents', function (Blueprint $table): void {
            $table->dropUnique('user_agents_device_identifier_unique');
            $table->dropColumn('device_identifier');
        });
    }
};
