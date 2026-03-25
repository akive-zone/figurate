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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->nullableMorphs('postable');
            $table->string('type');
            $table->string('tag')->nullable();
            $table->string('status');
            $table->json('data')->nullable();
            $table->json('meta')->nullable();
            $table->json('attachments')->nullable();
            $table->json('actions')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status', 'tag', 'created_at']);
            $table->index(['occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
