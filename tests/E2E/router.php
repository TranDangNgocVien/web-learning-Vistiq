<?php

// Test server only. Pin inherited environment before Laravel resolves storage/config paths.
$storage = getenv('LARAVEL_STORAGE_PATH');
if (getenv('APP_ENV') !== 'e2e' || ! $storage || ! is_file($storage.'/fixture.json')) {
    http_response_code(503);
    exit('Missing isolated E2E fixture');
}
$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;

return require dirname(__DIR__, 2).'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
