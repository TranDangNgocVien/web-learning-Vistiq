<?php

use App\Http\Controllers\SessionController;
use App\Services\FrontendContext;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/up', fn () => response()->json(['status' => 'ok', 'service' => 'Vistiq']))->name('health');
Route::get('/storage/{path}', function (string $path) {
    abort_if(str_contains($path, '..'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*');

Route::middleware('web')->group(function (): void {
    require __DIR__.'/web/learner.php';
    require __DIR__.'/web/admin.php';
});

foreach (array_values(Route::getRoutes()->getRoutes()) as $route) {
    $name = $route->getName();
    $uri = $route->uri();

    if ($name === null || str_starts_with($uri, 'api/') || ! in_array('web', $route->middleware(), true)) {
        continue;
    }

    $action = $route->getAction();
    unset($action['prefix']);
    $action['middleware'] = array_values(array_unique([
        'web',
        'api.response',
        ...array_values(array_diff($route->middleware(), ['web'])),
    ]));
    $action['as'] = 'api.'.$name;
    $surface = preg_match('#^admin(?:/|$)#', $uri) === 1 ? 'admin' : 'learner';
    $apiUri = app(FrontendContext::class)->apiPath('/'.ltrim($uri, '/'), $surface);

    Route::match($route->methods(), $apiUri, $action)->where($route->wheres);
}

Route::get('/api/v1/session', SessionController::class)->middleware('web')->name('api.session');
Route::get('/api/v1/admin/session', SessionController::class)->middleware('web')->name('api.admin.session');
Route::prefix('api')->middleware('api')->group(__DIR__.'/dictionary.php');
