<?php

namespace VistiqAdmin\Http\Controllers\Admin\Search;

use Illuminate\Http\Request;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\LearningItem;
use VistiqCore\Models\QuestionBankItem;

class AdminSearchController extends AdminPageController
{
    public function index(Request $request): mixed
    {
        $term = trim($request->string('q')->toString());
        $results = [];

        if ($term !== '') {
            foreach (['knowledge' => LearningItem::class, 'exams' => ExamTemplate::class, 'questions' => QuestionBankItem::class] as $kind => $model) {
                $field = match ($kind) {
                    'knowledge' => 'canonical_text',
                    'exams' => 'title',
                    'questions' => 'prompt',
                };

                foreach ($model::where($field, 'like', '%'.$term.'%')->limit(10)->get() as $item) {
                    $results[] = ['label' => strip_tags($item->{$field}), 'url' => route('admin.'.$kind.'.edit', $item->id)];
                }
            }
        }

        return $this->page('Tìm kiếm toàn hệ thống', 'pages.admin.search.workspace', compact('results', 'term'));
    }
}
