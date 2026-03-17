<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('name');
            $table->text('password')->nullable()->after('email');
            $table->unique('email', 'accounts_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropUnique('accounts_email_unique');
            $table->dropColumn(['email', 'password']);
        });
    }
};
