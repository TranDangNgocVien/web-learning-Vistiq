<?php

namespace VistiqAdmin\Http\Controllers\Admin\Learning\Knowledge;

use Illuminate\View\View;
use VistiqAdmin\Http\Controllers\StaticPageController;

class GrammarController extends StaticPageController
{
    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, [
            'admin-grammar' => ['title' => 'Biên tập ngữ pháp', 'frame' => 'A10', 'view' => 'pages.admin.learning.knowledge.legacy.grammar', 'section' => 'admin', 'description' => 'Quản lý chủ điểm, ví dụ và nội dung giải thích.'],
        ]);
    }
}
