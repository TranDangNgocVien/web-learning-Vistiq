<?php

namespace VistiqAdmin\Http\Controllers;

use Illuminate\View\View;

abstract class StaticPageController extends Controller
{
    /**
     * Render one static demo screen using its feature-owned page definition.
     *
     * @param  array{title: string, frame: string, view: string, section: string, description: string, variant?: string, show_heading?: bool}  $definition
     */
    protected function renderScreen(string $slug, array $definition): View
    {
        $page = [
            ...$definition,
            'slug' => $slug,
            'shell' => match ($definition['section']) {
                'admin' => 'admin',
                'auth' => 'auth',
                default => 'admin',
            },
        ];

        return view('shared.admin-demo-page', ['page' => $page]);
    }

    /**
     * @param  array<string, array{title: string, frame: string, view: string, section: string, description: string, variant?: string, show_heading?: bool}>  $screens
     */
    protected function renderKnownScreen(string $slug, array $screens): View
    {
        abort_unless(isset($screens[$slug]), 404);

        return $this->renderScreen($slug, $screens[$slug]);
    }
}
