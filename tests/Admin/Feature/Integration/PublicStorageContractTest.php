<?php

namespace Tests\Admin\Feature\Integration;

use Illuminate\Support\Facades\Storage;
use Tests\Admin\TestCase;

class PublicStorageContractTest extends TestCase
{
    public function test_public_avatar_route_is_not_shadowed_by_private_local_disk_route(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        Storage::disk('public')->put('avatars/123/picture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP1sAAAAASUVORK5CYII='));
        Storage::disk('local')->put('database-operations/private.sql', 'private backup');
        $this->get('/storage/avatars/123/picture.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/storage/database-operations/private.sql')->assertNotFound();
    }
}
