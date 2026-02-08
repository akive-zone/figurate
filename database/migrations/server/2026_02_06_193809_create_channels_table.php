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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('open');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['requester_id', 'status']);
            $table->index(['profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
