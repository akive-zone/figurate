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
        Schema::create('storeables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->morphs('storeable');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope')->default('default');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'storeable_type', 'storeable_id', 'scope'], 'storeables_unique');
            $table->index(['storeable_type', 'storeable_id', 'scope'], 'storeables_scope_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('storeables');
    }
};
