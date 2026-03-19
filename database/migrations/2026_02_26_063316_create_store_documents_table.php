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
        Schema::create('store_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('origin')->default('unknown');
            $table->string('provider_file_id')->nullable();
            $table->string('provider_document_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'media_id'], 'store_documents_store_media_unique');
            $table->index(['store_id', 'status'], 'store_documents_store_status_index');
            $table->index(['provider_file_id'], 'store_documents_provider_file_index');
            $table->index(['provider_document_id'], 'store_documents_provider_document_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_documents');
    }
};
