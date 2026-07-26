<?php

namespace Database\Factories;

use App\Models\Server\Store;
use App\Models\Server\StoreDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoreDocument>
 */
class StoreDocumentFactory extends Factory
{
    /**
     * @var class-string<StoreDocument>
     */
    protected $model = StoreDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'media_id' => 1,
            'post_id' => null,
            'origin' => 'unknown',
            'provider_file_id' => null,
            'provider_document_id' => null,
            'status' => 'pending',
            'meta' => null,
        ];
    }
}
