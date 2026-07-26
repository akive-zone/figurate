<?php

namespace App\Models\Server;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Laravel\Sanctum\PersonalAccessToken;

class ApiPersonalAccessToken extends PersonalAccessToken
{
    use HasUlids;

    protected $table = 'personal_access_tokens';

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }
}
