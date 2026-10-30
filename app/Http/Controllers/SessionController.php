<?php

namespace App\Http\Controllers;

use App\Http\Resources\PageResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

class SessionController
{
    public function __invoke(Request $request): PageResource
    {
        return new PageResource([
            'csrf_token' => csrf_token(),
            'user' => $request->user() ? new UserResource($request->user()) : null,
        ]);
    }
}
