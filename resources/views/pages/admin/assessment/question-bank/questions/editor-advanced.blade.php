    <label class="question-editor-wide" data-type-family="text">Đáp án chấp nhận<textarea name="accepted_answers_text" rows="3" placeholder="Mỗi dòng một đáp án">{{ implode("\n", old('accepted_answers', $payload['accepted_answers'] ?? [])) }}</textarea></label>
    <label class="question-editor-wide" data-type-family="matching">Các cặp matching<textarea name="answer_map_text" rows="4" placeholder="1 | A&#10;2 | C">@foreach(($metadata['answer_map'] ?? []) as $row => $answer){{ $row }} | {{ $answer }}{{ "\n" }}@endforeach</textarea><span class="form-help">Mỗi dòng: nhãn câu hỏi | mã phương án.</span></label>
    <label class="question-editor-wide" data-type-family="matching">Nhãn đoạn / nhóm<textarea name="metadata[labels_text]" rows="3" placeholder="A | Hướng dẫn thanh toán&#10;B | Thời gian giao hàng">@foreach(($metadata['labels'] ?? []) as $key => $label){{ $key }} | {{ $label }}{{ "\n" }}@endforeach</textarea></label>
    <label class="question-editor-wide" data-type-family="text">Các ô trống<textarea name="blanks_text" rows="4" placeholder="Nhãn ô | đáp án 1; đáp án 2">@foreach(($metadata['blanks'] ?? []) as $blank){{ ($blank['label'] ?? '') }} | {{ implode('; ', $blank['accepted_answers'] ?? []) }}{{ "\n" }}@endforeach</textarea><span class="form-help">Dùng khi một câu có nhiều ô cần điền.</span></label>

        <div data-type-family="speaking" class="question-editor-grid question-writing-fields">
            <label class="question-editor-wide">Cue card<textarea name="metadata[cue_card]" rows="3">{{ $metadata['cue_card'] ?? '' }}</textarea></label>
            <label class="question-editor-wide">Câu hỏi tiếp nối<textarea name="metadata[follow_ups_text]" rows="3" placeholder="Mỗi dòng một câu">{{ implode("\n", $metadata['follow_ups'] ?? []) }}</textarea></label>
        </div>
    <label class="question-editor-wide" data-type-family="essay speaking">Bài mẫu<textarea name="metadata[model_answer]" rows="4" maxlength="10000">{{ $metadata['model_answer'] ?? '' }}</textarea></label>
    <label data-type-family="essay">Số từ tối thiểu<input type="number" min="1" max="10000" name="metadata[min_words]" data-qe-min-words value="{{ $metadata['min_words'] ?? '' }}"><span class="form-help">Task 1 thường 150 từ, Task 2 thường 250 từ.</span></label>
    @foreach(['writing'=>'essay', 'speaking'=>'speaking'] as $rubricSkill=>$rubricFamily)
        <section data-type-family="{{ $rubricFamily }}" class="question-editor-grid question-writing-fields">
            <h3 class="question-editor-wide">Tiêu chí chấm {{ ucfirst($rubricSkill) }}</h3>
            <p class="form-help question-editor-wide">Nhập hướng dẫn cho đủ bốn tiêu chí trước khi gửi duyệt, tối đa 2.000 ký tự mỗi tiêu chí.</p>
            @foreach(\VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService::criteria($rubricSkill) as $criterion)
                <label>{{ \VistiqCore\Services\Assessment\QuestionBank\SubjectiveQuestionWorkbookService::CRITERIA_LABELS[$criterion] }} <em>*</em><textarea name="metadata[rubric][{{ $criterion }}]" rows="3" maxlength="2000">{{ $metadata['rubric'][$criterion] ?? '' }}</textarea></label>
            @endforeach
        </section>
    @endforeach
