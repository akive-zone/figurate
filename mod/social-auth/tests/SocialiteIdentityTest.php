<?php

namespace Figurate\SocialAuth\Tests;

use App\Models\Server\Identity;
use App\Models\Server\User;
use Figurate\AccountManager\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialiteIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_socialite_callback_creates_identity_and_attaches_it_to_the_primary_account(): void
    {
        Socialite::fake('google', (new SocialiteUser)->map([
            'id' => 'google-user-123',
            'name' => 'Studio Owner',
            'email' => 'owner@example.com',
            'nickname' => 'owner',
            'avatar' => 'https://example.com/avatar.png',
        ])->setToken('google-token')->setRefreshToken('google-refresh'));

        $response = $this->get('/auth/google/callback');

        $response->assertRedirect(route('chat.index'));

        $subjectUser = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $account = Account::query()->firstOrFail();
        $identity = Identity::query()
            ->where('provider', 'google')
            ->where('provider_subject', 'google-user-123')
            ->firstOrFail();

        $this->assertSame(User::TypeSubject, $subjectUser->type);
        $this->assertSame('owner@example.com', data_get($identity->payload, 'email'));
        $this->assertSame('owner', data_get($identity->payload, 'username'));
        $this->assertSame('google-token', data_get($identity->payload, 'tokens.access'));
        $this->assertSame('google-refresh', data_get($identity->payload, 'tokens.refresh'));
        $this->assertDatabaseHas('identity_relations', [
            'identity_id' => $identity->id,
            'relatable_type' => $subjectUser->getMorphClass(),
            'relatable_id' => $subjectUser->id,
            'unlinked_at' => null,
        ]);
        $this->assertDatabaseHas('identity_relations', [
            'identity_id' => $identity->id,
            'relatable_type' => $account->getMorphClass(),
            'relatable_id' => $account->id,
            'unlinked_at' => null,
        ]);
        $this->assertDatabaseHas('account_users', [
            'account_id' => $account->id,
            'user_id' => $subjectUser->id,
            'relationship' => 'owner',
            'is_primary' => true,
            'unlinked_at' => null,
        ]);
    }
}
