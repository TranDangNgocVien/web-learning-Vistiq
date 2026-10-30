<?php

namespace App\Http\Middleware;

use App\Http\Resources\PageResource;
use App\Http\Resources\UserResource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ApiResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($response instanceof RedirectResponse) {
            $errors = $request->session()->get('errors');
            if ($errors && $errors->any() && in_array('errors', $request->session()->get('_flash.new', []), true)) {
                $messages = $errors->getBag('default')->messages();

                return response()->json(['message' => collect($messages)->flatten()->first(), 'errors' => $messages], 422);
            }

            return (new PageResource([
                'redirect' => $response->getTargetUrl(),
                'message' => $request->session()->get('status'),
                'csrf_token' => csrf_token(),
                'user' => $request->user() ? new UserResource($request->user()) : null,
            ]))->response()->setStatusCode($request->isMethod('POST') && str_ends_with($request->path(), '/register') ? 201 : 200);
        }

        $content = method_exists($response, 'getOriginalContent') ? $response->getOriginalContent() : null;
        if ($content instanceof View) {
            return (new PageResource($content->getData()))->response()->setStatusCode($response->getStatusCode());
        }

        return $response;
    }
}
