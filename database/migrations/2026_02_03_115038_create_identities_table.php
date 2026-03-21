<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('provider', 120);
            $table->string('provider_subject', 191);
            $table->json('payload')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subject'], 'identities_provider_subject_unique');
            $table->index(['provider', 'last_used_at'], 'identities_provider_last_used_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identities');
    }
};
