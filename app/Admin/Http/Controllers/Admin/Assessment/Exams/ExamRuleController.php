<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\Exams;

use Illuminate\View\View;
use VistiqAdmin\Http\Controllers\StaticPageController;

class ExamRuleController extends StaticPageController
{
    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, [
            'admin-rules' => ['title' => 'Quy định thi', 'frame' => 'A05', 'view' => 'pages.admin.assessment.exams.legacy.rules', 'section' => 'admin', 'description' => 'Thiết lập thời gian, hướng dẫn và điều kiện làm bài.'],
        ]);
    }
}
