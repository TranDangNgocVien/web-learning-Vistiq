<?php

namespace VistiqLearner\Http\Controllers\Site;

use Illuminate\View\View;
use VistiqLearner\Http\Controllers\StaticPageController;

class HomeController extends StaticPageController
{
    private const SCREENS = [
        'home' => ['title' => 'Trang chủ', 'frame' => 'U01', 'view' => 'pages.home.index', 'section' => 'home', 'description' => 'Tra cứu nhanh. Luyện đúng trọng tâm.', 'show_heading' => false],
    ];

    public function index(): mixed
    {
        if ((! auth('web')->check() || auth('web')->user()->isAdmin()) && auth('admin')->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if (auth('web')->user()?->isAdmin()) {
            auth('web')->logout();

            return redirect()->route('admin.login');
        }

        return $this->show('home');
    }

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }
}
