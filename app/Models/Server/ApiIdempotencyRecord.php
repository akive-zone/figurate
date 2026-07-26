<?php

namespace App\Models\Server;

use Database\Factories\Server\ApiIdempotencyRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyRecord extends Model
{
    /** @use HasFactory<ApiIdempotencyRecordFactory> */
    use HasFactory;

    protected $table = 'idempotency_records';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'scope',
        'idempotency_key',
        'request_hash',
        'status_code',
        'response_body',
        'response_headers',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_headers' => 'array',
        ];
    }
}
