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
        Schema::create('thread_observers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('threads')->cascadeOnDelete();
            $table->string('observer_key');
            $table->string('mode')->default('passive');
            $table->string('status')->default('active');
            $table->json('config')->nullable();
            $table->timestamps();

            $table->unique(['thread_id', 'observer_key'], 'thread_observers_unique');
            $table->index(['observer_key', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_observers');
    }
};
