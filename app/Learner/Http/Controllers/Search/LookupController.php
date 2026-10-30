<?php

namespace VistiqLearner\Http\Controllers\Search;

use Illuminate\View\View;
use VistiqLearner\Http\Controllers\StaticPageController;
use VistiqLearner\Http\Requests\Search\DictionaryLookupRequest;
use VistiqLearner\Models\LearningItem;

class LookupController extends StaticPageController
{
    private const SCREENS = [
        'lookup' => ['title' => 'Tra cứu nhanh', 'frame' => 'U02', 'view' => 'pages.learner.search.dictionary.index', 'section' => 'lookup', 'description' => 'Từ vựng, collocation, phrasal verb và ngữ pháp trong cùng một nơi.', 'show_heading' => false],
        'lookup-overview' => ['title' => 'Achieve', 'frame' => 'U03', 'view' => 'pages.learner.search.dictionary.entry', 'section' => 'lookup', 'description' => 'Xem nghĩa, ví dụ và cách dùng trong ngữ cảnh.', 'variant' => 'overview', 'show_heading' => false],
        'lookup-collocations' => ['title' => 'Achieve · Collocation', 'frame' => 'U32', 'view' => 'pages.learner.search.dictionary.entry', 'section' => 'lookup', 'description' => 'Các cụm từ thường đi cùng với achieve.', 'variant' => 'collocations', 'show_heading' => false],
        'lookup-examples' => ['title' => 'Achieve · Ví dụ', 'frame' => 'U33', 'view' => 'pages.learner.search.dictionary.entry', 'section' => 'lookup', 'description' => 'Xem cách dùng achieve qua ví dụ thực tế.', 'variant' => 'examples', 'show_heading' => false],
    ];

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }

    public function index(DictionaryLookupRequest $request): View
    {
        $term = $request->validated('q') ?? '';
        $type = $request->validated('type') ?? 'all';
        $results = $term !== '' || $type !== 'all' ? LearningItem::search($term, $type)->paginate(12)->withQueryString() : null;

        return view('shared.demo-page', [
            'page' => $this->lookupPage('Tra cứu từ vựng và ngữ pháp', 'pages.learner.search.dictionary.search'),
            'term' => $term, 'type' => $type, 'results' => $results,
            'entry' => $results?->first()?->lookupDetails(),
            'activeTab' => $request->validated('tab') ?? 'overview',
        ]);
    }

    public function entry(DictionaryLookupRequest $request, string $slug): View
    {
        $item = LearningItem::publicLookup()->where('learning_items.slug', $slug)->firstOrFail();
        $term = $request->validated('q') ?? $item->canonical_text;
        $type = $request->validated('type') ?? 'all';
        $results = LearningItem::search($term, $type)->paginate(12)->withQueryString();
        $results->withPath(route('lookup.index'));
        $results->appends(['q' => $term, 'type' => $type]);

        return view('shared.demo-page', [
            'page' => $this->lookupPage($item->canonical_text, 'pages.learner.search.dictionary.detail'),
            'entry' => $item->lookupDetails(), 'term' => $term, 'type' => $type, 'results' => $results,
            'activeTab' => $request->validated('tab') ?? 'overview',
        ]);
    }

    /** @return array<string, mixed> */
    private function lookupPage(string $title, string $view): array
    {
        return ['title' => $title, 'slug' => 'lookup', 'shell' => 'user', 'section' => 'lookup', 'view' => $view, 'show_heading' => false];
    }
}
