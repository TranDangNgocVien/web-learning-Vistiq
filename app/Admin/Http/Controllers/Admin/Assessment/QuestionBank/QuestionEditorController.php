<?php

namespace VistiqAdmin\Http\Controllers\Admin\Assessment\QuestionBank;

use Illuminate\Http\Request;
use VistiqAdmin\Http\Controllers\Controller;
use VistiqCore\Models\ExamTemplate;
use VistiqCore\Models\QuestionBankItem;
use VistiqCore\Services\Assessment\QuestionBank\QuestionEditorService;

class QuestionEditorController extends Controller
{
    public function catalog(Request $request, QuestionEditorService $editor): mixed
    {
        $data = $request->validate(['kind' => 'required|in:tag,knowledge,image,audio,stimulus,exam', 'q' => 'nullable|string|max:255', 'part_id' => 'required_if:kind,exam|nullable|integer|exists:exam_parts,id']);

        return response()->json(['items' => $editor->catalog($data['kind'], $data['q'] ?? '', isset($data['part_id']) ? (int) $data['part_id'] : null)]);
    }

    public function history(Request $request, QuestionBankItem $question, QuestionEditorService $editor): mixed
    {
        $data = $request->validate(['page' => 'nullable|integer|min:1']);

        return response()->json($editor->history($question, (int) ($data['page'] ?? 1)));
    }

    public function links(QuestionBankItem $question, QuestionEditorService $editor): mixed
    {
        return response()->json(['items' => $editor->examLinks($question)]);
    }

    public function attach(Request $request, QuestionBankItem $question, ExamTemplate $exam, QuestionEditorService $editor): mixed
    {
        $data = $request->validate(['revision_counter' => 'required|integer|min:0']);

        return response()->json(['items' => $editor->changeExamLink($question, $exam, true, $data['revision_counter'])]);
    }

    public function detach(Request $request, QuestionBankItem $question, ExamTemplate $exam, QuestionEditorService $editor): mixed
    {
        $data = $request->validate(['revision_counter' => 'required|integer|min:0']);

        return response()->json(['items' => $editor->changeExamLink($question, $exam, false, $data['revision_counter'])]);
    }
}
