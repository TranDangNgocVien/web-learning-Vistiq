<?php

namespace VistiqLearner\Http\Controllers\Knowledge;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use VistiqCore\Services\Knowledge\KnowledgeReaderService;
use VistiqLearner\Http\Controllers\StaticPageController;
use VistiqLearner\Http\Requests\Knowledge\KnowledgeFilterRequest;

class KnowledgeController extends StaticPageController
{
    public function show(string $screen): RedirectResponse
    {
        return redirect()->route($screen === 'knowledge' ? 'knowledge.index' : 'knowledge.saved');
    }

    public function index(KnowledgeFilterRequest $request, KnowledgeReaderService $reader): View
    {
        $page = ['title' => 'Kho kiến thức', 'view' => 'pages.learner.knowledge.saved.library', 'section' => 'knowledge', 'shell' => 'user', 'slug' => 'knowledge', 'show_heading' => false];

        return view('shared.demo-page', ['page' => $page, ...$reader->library($request->user()->id, $request->validated())]);
    }

    public function savedList(KnowledgeFilterRequest $request, KnowledgeReaderService $reader): View
    {
        return $this->index($request, $reader);
    }

    private function catalog(mixed $items, array $filters, bool $savedView = false): View
    {
        $page = ['title' => $savedView ? 'Mục đã lưu' : 'Kho kiến thức', 'view' => 'pages.learner.knowledge.saved.index', 'section' => 'knowledge', 'shell' => 'user', 'slug' => 'knowledge', 'show_heading' => false];

        return view('shared.demo-page', ['page' => $page, 'items' => $items, 'filters' => $filters, 'savedView' => $savedView,
            'types' => DB::table('learning_item_types')->where('code', '!=', 'question')->orderBy('sort_order')->get(),
            'levels' => DB::table('proficiency_levels')->orderBy('sort_order')->get()]);
    }

    public function detail(string $slug, KnowledgeReaderService $reader): View
    {
        $data = $reader->detail($slug);
        $page = ['title' => $data['summary']->canonical_text, 'view' => 'pages.learner.knowledge.saved.show', 'section' => 'knowledge', 'shell' => 'user', 'slug' => $slug, 'show_heading' => false];
        $saved = auth()->check() ? DB::table('user_saved_items')->where('user_id', auth()->id())->where('learning_item_id', $data['summary']->id)->first() : null;

        return view('shared.demo-page', ['page' => $page, ...$data, 'saved' => $saved]);
    }

    public function save(Request $request, string $slug, KnowledgeReaderService $reader): mixed
    {
        $data = $request->validate(['personal_note' => 'nullable|string|max:5000', 'is_starred' => 'sometimes|boolean', 'collection_id' => ['nullable', 'integer', Rule::exists('user_knowledge_collections', 'id')->where('user_id', $request->user()->id)]]);
        $item = $reader->detail($slug)['summary'];
        $existing = DB::table('user_saved_items')->where('user_id', $request->user()->id)->where('learning_item_id', $item->id)->first();
        DB::table('user_saved_items')->updateOrInsert(['user_id' => $request->user()->id, 'learning_item_id' => $item->id], [
            'personal_note' => array_key_exists('personal_note', $data) ? $data['personal_note'] : $existing?->personal_note, 'is_starred' => $data['is_starred'] ?? $existing?->is_starred ?? false,
            'collection_id' => array_key_exists('collection_id', $data) ? $data['collection_id'] : $existing?->collection_id,
            'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
        ]);

        return $request->expectsJson() ? response()->json(['saved' => true, 'message' => 'Đã lưu vào kho kiến thức.']) : back()->with('status', 'Đã lưu mục kiến thức và ghi chú.');
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('user_knowledge_collections', 'name')->where('user_id', $request->user()->id)]]);
        DB::table('user_knowledge_collections')->insert(['user_id' => $request->user()->id, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('status', 'Đã tạo bộ sưu tập.');
    }

    public function unsave(Request $request, string $slug, KnowledgeReaderService $reader): mixed
    {
        $item = $reader->detail($slug)['summary'];
        DB::table('user_saved_items')->where('user_id', $request->user()->id)->where('learning_item_id', $item->id)->delete();

        return $request->expectsJson() ? response()->json(['saved' => false, 'message' => 'Đã bỏ lưu mục kiến thức.']) : redirect()->route('knowledge.saved')->with('status', 'Đã bỏ lưu mục kiến thức.');
    }
}
