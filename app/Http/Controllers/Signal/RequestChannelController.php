<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use App\Models\Server\Profile;
use Inertia\Inertia;
use Inertia\Response;

class RequestChannelController extends Controller
{
    public function create(): Response
    {
        $profiles = Profile::query()
            ->where('status', 'approved')
            ->select(['id', 'display_name', 'location'])
            ->orderBy('display_name')
            ->limit(100)
            ->get();

        return Inertia::render('Signal/Requests/Create', [
            'profiles' => $profiles,
        ]);
    }
}
