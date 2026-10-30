<?php

namespace App\Routing;

use App\Http\Resources\PageResource;
use Illuminate\Routing\ControllerDispatcher;
use Illuminate\Routing\Route;
use Illuminate\View\View;

class ApiControllerDispatcher extends ControllerDispatcher
{
    public function dispatch(Route $route, mixed $controller, mixed $method): mixed
    {
        $result = parent::dispatch($route, $controller, $method);

        return request()->is('api/*') && $result instanceof View
            ? new PageResource($result->getData())
            : $result;
    }
}
