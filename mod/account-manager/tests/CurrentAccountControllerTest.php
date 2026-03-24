<?php

namespace Figurate\AccountManager\Tests;

use App\Models\Server\User;
use Figurate\AccountManager\Contracts\AccountContext as AccountContextContract;
use Figurate\AccountManager\Http\Controllers\Api\CurrentAccountController;
use Figurate\AccountManager\Models\Account;
use Figurate\AccountManager\Support\AccountContextFactory;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CurrentAccountControllerTest extends TestCase
{
    public function test_it_rejects_widget_users_when_resolving_the_current_account(): void
    {
        $accountContextFactory = Mockery::mock(AccountContextFactory::class);
        $accountContextFactory->shouldNotReceive('forUser');

        $controller = new CurrentAccountController($accountContextFactory);
        $request = Request::create('/api/accounts/current', 'GET');
        $request->setUserResolver(fn (): User => new User([
            'name' => 'Widget User',
            'email' => 'widget@example.com',
            'type' => User::TypeWidget,
            'status' => 'active',
        ]));

        $response = $controller->show($request);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('A subject account is required.', $response->getData(true)['message']);
    }

    public function test_it_returns_the_primary_account_for_subject_users(): void
    {
        $subjectUser = new User([
            'name' => 'Subject User',
            'email' => 'subject@example.com',
            'type' => User::TypeSubject,
            'status' => 'active',
        ]);
        $account = new Account([
            'uuid' => '019d1111-1111-7111-8111-111111111111',
            'name' => 'Studio Owner',
            'status' => 'active',
        ]);
        $account->setAttribute('id', 42);

        $accountContext = Mockery::mock(AccountContextContract::class);
        $accountContext->shouldReceive('primaryAccount')
            ->once()
            ->andReturn($account);

        $accountContextFactory = Mockery::mock(AccountContextFactory::class);
        $accountContextFactory->shouldReceive('forUser')
            ->once()
            ->with($subjectUser)
            ->andReturn($accountContext);

        $controller = new CurrentAccountController($accountContextFactory);
        $request = Request::create('/api/accounts/current', 'GET');
        $request->setUserResolver(fn () => $subjectUser);

        $response = $controller->show($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'data' => [
                'id' => 42,
                'uuid' => '019d1111-1111-7111-8111-111111111111',
                'name' => 'Studio Owner',
                'status' => 'active',
            ],
        ], $response->getData(true));
    }
}
