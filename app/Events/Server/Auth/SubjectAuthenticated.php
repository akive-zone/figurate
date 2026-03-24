<?php

namespace App\Events\Server\Auth;

use App\Features\Actions\Auth\ResolveWidgetUser;
use App\Models\Server\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class SubjectAuthenticated
{
    use Dispatchable, SerializesModels;

    /**
     * @var array{
     *     headers: array<string, mixed>,
     *     cookies: array<string, mixed>,
     *     user_agent: ?string,
     *     ip_address: ?string,
     *     expects_json: bool,
     *     path: string
     * }
     */
    public array $widgetUserContext;

    public function __construct(
        public User $user,
        Request $request,
        public string $action,
    ) {
        $this->widgetUserContext = ResolveWidgetUser::contextFromRequest($request);
    }
}
