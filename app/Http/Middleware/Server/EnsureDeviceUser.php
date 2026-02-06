<?php

namespace App\Http\Middleware;

use App\Models\Server\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        $deviceId = $request->header('X-Device-Id')
            ?? $request->cookie('device_id')
            ?? (string) Str::uuid();

        if (! $request->cookie('device_id')) {
            Cookie::queue(cookie()->forever('device_id', $deviceId));
        }

        $user = User::firstOrCreate(
            ['device_identifier' => $deviceId],
            [
                'name' => 'Device User',
                'email' => "device-{$deviceId}@example.invalid",
                'password' => Hash::make(Str::random(48)),
                'type' => 'device',
                'status' => 'active',
            ]
        );

        Auth::login($user);

        return $next($request);
    }
}
