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
        Schema::create('context_servers', function (Blueprint $table): void {
            $table->id();
            $table->morphs('contextable');
            $table->string('server');
            $table->string('label')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->string('transport')->default('remote');
            $table->string('endpoint_url')->nullable();
            $table->string('handler')->nullable();
            $table->json('allowed_tools')->nullable();
            $table->string('auth_type')->nullable();
            $table->text('credentials')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contextable_type', 'contextable_id', 'server', 'enabled'], 'context_servers_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('context_servers');
    }
};
