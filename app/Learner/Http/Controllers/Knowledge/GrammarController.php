<?php

namespace VistiqLearner\Http\Controllers\Knowledge;

use Illuminate\Http\RedirectResponse;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\View\View;
use VistiqCore\Services\Knowledge\KnowledgeReaderService;
use VistiqLearner\Http\Controllers\StaticPageController;
use VistiqLearner\Http\Requests\Knowledge\KnowledgeFilterRequest;

class GrammarController extends StaticPageController
{
    private const SCREENS = [
        'grammar' => ['title' => 'Thư viện ngữ pháp', 'frame' => 'U04', 'view' => 'pages.learner.knowledge.grammar.index', 'section' => 'grammar', 'description' => 'Học ngữ pháp theo chủ điểm, cấp độ và lộ trình.', 'show_heading' => false],
        'grammar-present-perfect' => ['title' => 'Present Perfect', 'frame' => 'U05', 'view' => 'pages.learner.knowledge.grammar.show', 'section' => 'grammar', 'description' => 'Cấu trúc, cách dùng và ví dụ dễ nhớ.', 'show_heading' => false],
    ];

    public function show(string $screen): RedirectResponse
    {
        return redirect()->route($screen === 'grammar' ? 'grammar.index' : 'grammar.show', $screen === 'grammar' ? [] : ['slug' => 'present-perfect']);
    }

    public function index(KnowledgeFilterRequest $request): View
    {
        $grammarTypeId = DB::table('learning_item_types')->where('code', 'grammar')->value('id');
        $q = $request->validated('q');
        $category = $request->validated('category');
        $level = $request->validated('level');

        $query = DB::table('learning_items')
            ->join('grammar_topics', 'grammar_topics.learning_item_id', '=', 'learning_items.id')
            ->leftJoin(DB::source('grammar_categories'), 'grammar_categories.id', '=', 'grammar_topics.grammar_category_id')
            ->leftJoin(DB::source('proficiency_levels'), 'proficiency_levels.id', '=', 'learning_items.proficiency_level_id')
            ->where('learning_items.status', 'published')
            ->where(fn ($sub) => $sub->whereNull('learning_items.published_at')->orWhere('learning_items.published_at', '<=', now()))
            ->when($grammarTypeId, fn ($builder) => $builder->where('learning_items.learning_item_type_id', $grammarTypeId))
            ->when($q, function ($builder) use ($q): void {
                $builder->where(function ($sub) use ($q): void {
                    $sub->where('learning_items.canonical_text', 'like', "%{$q}%")
                        ->orWhere('grammar_topics.summary', 'like', "%{$q}%");
                });
            })
            ->when($category, function ($builder) use ($category): void {
                $builder->where(function ($sub) use ($category): void {
                    $sub->where('grammar_categories.slug', $category)
                        ->orWhere('grammar_categories.name', $category);
                });
            })
            ->when($level, function ($builder) use ($level): void {
                $builder->where(function ($sub) use ($level): void {
                    $sub->where('proficiency_levels.code', $level)
                        ->orWhere('proficiency_levels.label', $level);
                });
            })
            ->select([
                'learning_items.id',
                'learning_items.canonical_text',
                'learning_items.slug',
                'grammar_topics.summary',
                'grammar_categories.name as category_name',
                'grammar_categories.slug as category_slug',
                'proficiency_levels.code as level_code',
                'proficiency_levels.label as level_label',
            ])
            ->orderBy('learning_items.canonical_text');

        $items = $query->paginate(12)->withQueryString();
        $categories = DB::table('grammar_categories')->orderBy('name')->get();
        $levels = DB::table('proficiency_levels')->orderBy('sort_order')->get();

        $page = [
            'title' => 'Thư viện ngữ pháp',
            'view' => 'pages.learner.knowledge.grammar.index',
            'section' => 'grammar',
            'shell' => 'user',
            'slug' => 'grammar',
            'description' => 'Học ngữ pháp theo chủ điểm, cấp độ và lộ trình.',
            'show_heading' => false,
        ];

        return view('shared.demo-page', [
            'page' => $page,
            'items' => $items,
            'categories' => $categories,
            'levels' => $levels,
            'filters' => [
                'q' => $q,
                'category' => $category,
                'level' => $level,
            ],
        ]);
    }

    public function detail(string $slug, KnowledgeReaderService $reader): View
    {
        $data = $reader->detail($slug);
        abort_unless($data['summary']->type_code === 'grammar', 404);
        $page = ['title' => $data['summary']->canonical_text, 'view' => 'pages.learner.knowledge.saved.show', 'section' => 'grammar', 'shell' => 'user', 'slug' => $slug, 'show_heading' => false];
        $saved = auth()->check() ? DB::table('user_saved_items')->where('user_id', auth()->id())->where('learning_item_id', $data['summary']->id)->first() : null;

        return view('shared.demo-page', ['page' => $page, ...$data, 'saved' => $saved]);
    }
}
