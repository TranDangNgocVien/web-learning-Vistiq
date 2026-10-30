<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;

class SubjectiveQuestionWorkbookService
{
    public const CRITERIA_LABELS = [
        'task_response' => 'Đáp ứng yêu cầu đề bài', 'coherence' => 'Mạch lạc và liên kết',
        'lexical_resource' => 'Vốn từ vựng', 'grammar' => 'Ngữ pháp',
        'fluency' => 'Độ trôi chảy và mạch lạc', 'pronunciation' => 'Phát âm',
    ];

    public static function criteria(string $skill): array
    {
        return $skill === 'writing' ? QuestionRegistry::WRITING_CRITERIA : QuestionRegistry::SPEAKING_CRITERIA;
    }

    public static function defaultRubric(string $skill): array
    {
        return array_combine(self::criteria($skill), array_map(fn ($key) => 'Đánh giá '.mb_strtolower(self::CRITERIA_LABELS[$key]).' theo thang IELTS 0–9.', self::criteria($skill)));
    }

    private function headers(string $skill): array
    {
        return array_merge(['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'difficulty', 'variant'], $skill === 'writing' ? ['min_words'] : ['cue_card', 'follow_ups'], ['model_answer', 'explanation', 'tags', 'image_id'], array_map(fn ($key) => 'rubric_'.$key, self::criteria($skill)));
    }

    public function preview(string $path, string $skill): array
    {
        abort_unless(in_array($skill, ['writing', 'speaking'], true), 422);
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $workbook = $reader->load($path);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['file' => 'Không đọc được workbook .xlsx. Hãy kiểm tra tệp hoặc tải lại file mẫu.']);
        }
        try {
            $worksheets = array_values(array_filter($workbook->getAllSheets(), fn ($sheet) => Str::lower(trim($sheet->getTitle())) !== 'huong_dan'));
            if (count($worksheets) < 1 || count($worksheets) > 50) {
                throw ValidationException::withMessages(['file' => 'Workbook cần từ 1 đến 50 sheet dữ liệu.']);
            }
            $parts = ExamPart::with(['examType', 'skill'])->whereHas('skill', fn ($query) => $query->where('code', $skill))->get();
            $type = $skill === 'writing' ? 'essay' : 'speaking';
            $typeId = QuestionType::where('code', $type)->value('id');
            $difficulties = QuestionDifficulty::pluck('id', 'code');
            $sheets = [];
            $totalRows = 0;
            foreach ($worksheets as $worksheet) {
                $sheet = ['code' => $worksheet->getTitle(), 'title' => $worksheet->getTitle(), 'questions' => [], 'question_count' => 0, 'errors' => [], 'blocked' => false, 'exam_type' => null, 'skill' => $skill, 'exam_part' => null];
                if ($worksheet->getHighestDataRow() > 2001 || \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestDataColumn()) > 50) {
                    $sheet['errors'][] = ['row' => 1, 'message' => 'Mỗi sheet tối đa 2.000 dòng câu hỏi và 50 cột.'];
                    $sheet['blocked'] = true;
                    $sheets[] = $sheet;
                    continue;
                }
                $rows = $worksheet->toArray(null, true, true, false);
                $headers = array_map(fn ($value) => Str::lower(trim((string) $value)), array_shift($rows) ?? []);
                $usedHeaders = array_filter($headers, fn ($value) => $value !== '');
                $required = array_merge(['exam_type', 'skill', 'exam_part', 'question_type', 'prompt', 'difficulty'], array_map(fn ($key) => 'rubric_'.$key, self::criteria($skill)));
                $missing = array_diff($required, $headers);
                if ($missing || count($usedHeaders) !== count(array_unique($usedHeaders))) {
                    $sheet['errors'][] = ['row' => 1, 'message' => $missing ? 'Thiếu cột: '.implode(', ', $missing).'.' : 'Tiêu đề cột bị trùng.'];
                    $sheet['blocked'] = true;
                    $sheets[] = $sheet;
                    continue;
                }
                foreach ($rows as $index => $cells) {
                    if (collect($cells)->every(fn ($value) => trim((string) $value) === '')) continue;
                    if (++$totalRows > 2000) {
                        $sheet['errors'][] = ['row' => $index + 2, 'message' => 'Mỗi workbook tối đa 2.000 câu hỏi.'];
                        break;
                    }
                    $row = array_combine($headers, array_pad(array_slice($cells, 0, count($headers)), count($headers), null));
                    $part = $parts->first(fn ($candidate) => $candidate->code === trim((string) ($row['exam_part'] ?? '')) && $candidate->examType?->code === trim((string) ($row['exam_type'] ?? '')));
                    $rubric = [];
                    foreach (self::criteria($skill) as $key) $rubric[$key] = trim((string) ($row['rubric_'.$key] ?? ''));
                    $metadata = ['rubric' => $rubric];
                    foreach (['variant', 'model_answer', 'cue_card', 'min_words', 'image_id'] as $key) {
                        if (filled($row[$key] ?? null)) $metadata[$key] = trim((string) $row[$key]);
                    }
                    if (filled($row['follow_ups'] ?? null)) $metadata['follow_ups'] = preg_split('/\R/', trim((string) $row['follow_ups']), -1, PREG_SPLIT_NO_EMPTY);
                    $difficulty = trim((string) ($row['difficulty'] ?? '')) ?: 'medium';
                    $attributes = [
                        'exam_part_id' => $part?->id, 'question_type_id' => $typeId, 'question_difficulty_id' => $difficulties[$difficulty] ?? null,
                        'prompt' => trim((string) ($row['prompt'] ?? '')), 'explanation' => trim((string) ($row['explanation'] ?? '')),
                        'tags' => trim((string) ($row['tags'] ?? '')), 'metadata' => $metadata, 'status' => 'draft',
                    ];
                    $validator = Validator::make($attributes, [
                        'exam_part_id' => 'required|integer', 'question_type_id' => 'required|integer', 'question_difficulty_id' => 'required|integer',
                        'prompt' => 'required|string|max:10000', 'explanation' => 'nullable|string|max:10000', 'tags' => 'nullable|string|max:500',
                        'metadata.rubric.*' => 'required|string|max:2000', 'metadata.model_answer' => 'nullable|string|max:10000',
                    ], ['exam_part_id.required' => 'Kỳ thi và Part không khớp hoặc không thuộc '.$skill.'.', 'question_difficulty_id.required' => 'Độ khó chỉ nhận easy, medium hoặc hard.', 'metadata.rubric.*.required' => 'Cần nhập đủ hướng dẫn cho bốn tiêu chí chấm.', 'prompt.required' => 'Câu hỏi cần nội dung.']);
                    if (trim((string) ($row['skill'] ?? '')) !== $skill) $validator->after(fn ($v) => $v->errors()->add('skill', 'skill phải là '.$skill.'.'));
                    if (trim((string) ($row['question_type'] ?? '')) !== $type) $validator->after(fn ($v) => $v->errors()->add('type', 'question_type phải là '.$type.'.'));
                    try {
                        $validator->validate();
                        app(QuestionRegistry::class)->validate(array_merge($attributes, ['type' => $type, 'skill' => $skill, 'exam_type' => $part->examType->code, 'part' => $part->code]));
                    } catch (ValidationException $exception) {
                        foreach (collect($exception->errors())->flatten()->unique() as $message) $sheet['errors'][] = ['row' => $index + 2, 'message' => $message];
                        continue;
                    }
                    $sheet['questions'][] = ['row' => $index + 2, 'attributes' => $attributes, 'part_name' => $part->name];
                    $sheet['question_count']++;
                    $sheet['exam_type'] ??= $part->examType->code;
                    $sheet['exam_part'] ??= $part->code;
                }
                if (! $sheet['question_count'] && ! $sheet['errors']) $sheet['errors'][] = ['row' => 2, 'message' => 'Sheet chưa có câu hỏi.'];
                $sheet['blocked'] = $sheet['errors'] !== [];
                $sheets[] = $sheet;
            }
            $count = array_sum(array_column($sheets, 'question_count'));

            return ['kind' => $skill, 'sheets' => $sheets, 'total_questions' => $count, 'can_import' => $count > 0 && collect($sheets)->every(fn ($sheet) => ! $sheet['blocked'])];
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    public function template(string $skill): Spreadsheet
    {
        abort_unless(in_array($skill, ['writing', 'speaking'], true), 422);
        $parts = ExamPart::with('examType')->whereHas('skill', fn ($query) => $query->where('code', $skill))->orderBy('sort_order')->get();
        $concrete = $parts->filter(fn ($part) => str_contains($part->code, $skill === 'writing' ? 'task-' : 'part-'));
        $samples = $concrete->isNotEmpty() ? $concrete : $parts;
        abort_if($samples->isEmpty(), 422, 'Chưa có Part cho '.ucfirst($skill).' trong danh mục kỳ thi.');
        $workbook = new Spreadsheet;
        $guide = $workbook->getActiveSheet()->setTitle('HUONG_DAN');
        $guide->fromArray([
            ['HƯỚNG DẪN IMPORT CÂU HỎI '.strtoupper($skill), ''],
            ['Định dạng', 'File .xlsx tối đa 20 MB; tối đa 50 sheet và 2.000 câu hỏi mỗi workbook.'],
            ['Sheet dữ liệu', 'Mỗi dòng là một câu hỏi. Có thể thêm, đổi tên và nhân bản sheet; HUONG_DAN được bỏ qua.'],
            ['Phân loại', 'Chọn kỳ thi, skill, exam_part và question_type từ danh sách trong Excel.'],
            ['Nội dung', 'prompt bắt buộc, tối đa 10.000 ký tự. difficulty: easy, medium hoặc hard.'],
            ['Tiêu chí chấm', 'Nhập đủ bốn cột rubric, tối đa 2.000 ký tự mỗi tiêu chí. Mẫu đã điền hướng dẫn cơ bản.'],
            ['Bài mẫu', 'model_answer và explanation không bắt buộc; mỗi ô tối đa 10.000 ký tự.'],
            ['Kỹ năng', $skill === 'writing' ? 'min_words: 1–10.000; variant: academic hoặc general. Task 1 thường 150 từ, Task 2 thường 250 từ.' : 'cue_card: nội dung thẻ chủ đề. follow_ups: mỗi câu một dòng trong cùng ô (Alt+Enter).'],
            ['Hình ảnh', 'image_id không bắt buộc: ID ảnh đã có trong thư viện tài nguyên. Có thể gắn ảnh trong trình soạn sau khi import.'],
            ['Xem trước và lưu', 'Mọi sheet/dòng phải hợp lệ trước khi xác nhận. Toàn bộ câu hỏi được lưu bản nháp, chưa tự gửi duyệt.'],
        ]);
        $guide->getColumnDimension('A')->setWidth(25);
        $guide->getColumnDimension('B')->setWidth(105);
        $guide->getStyle('A1:B10')->getAlignment()->setWrapText(true);
        $headers = $this->headers($skill);
        foreach ($samples as $index => $part) {
            $sheet = $workbook->createSheet()->setTitle(strtoupper($skill).'_'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT));
            $isSecondTask = str_ends_with($part->code, '2');
            $row = [
                'exam_type' => $part->examType->code, 'skill' => $skill, 'exam_part' => $part->code, 'question_type' => $skill === 'writing' ? 'essay' : 'speaking',
                'prompt' => $skill === 'writing' ? ($isSecondTask ? 'Some people prefer to study alone, while others prefer group study. Discuss both views and give your opinion.' : 'Summarise the following data: cycling to work increased from 15% in 2010 to 30% in 2020, while driving decreased from 60% to 45%.') : 'Describe a place you enjoy visiting and explain why it is special to you.',
                'difficulty' => 'medium', 'variant' => 'academic', 'min_words' => $isSecondTask ? 250 : 150,
                'cue_card' => 'You should say where it is, when you visit, what you do there, and why you enjoy it.',
                'follow_ups' => "Why do people enjoy visiting new places?\nHow has travel changed in recent years?",
            ];
            foreach (self::defaultRubric($skill) as $key => $guidance) $row['rubric_'.$key] = $guidance;
            $sheet->fromArray([$headers, array_map(fn ($key) => $row[$key] ?? '', $headers)]);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
            foreach ($headers as $column => $header) {
                $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column + 1);
                $sheet->getColumnDimension($letter)->setWidth(in_array($header, ['prompt', 'model_answer', 'cue_card', 'follow_ups'], true) || str_starts_with($header, 'rubric_') ? 48 : 22);
                $lists = ['exam_type' => $parts->pluck('examType.code')->unique()->values()->all(), 'skill' => [$skill], 'exam_part' => $parts->pluck('code')->all(), 'question_type' => [$row['question_type']], 'difficulty' => ['easy', 'medium', 'hard'], 'variant' => ['academic', 'general']];
                if (isset($lists[$header]) && $lists[$header] !== []) {
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(in_array($header, ['variant', 'difficulty'], true))->setShowErrorMessage(true)->setError('Chọn giá trị trong danh sách.')->setFormula1('"'.implode(',', $lists[$header]).'"');
                    $sheet->setDataValidation($letter.'2:'.$letter.'2001', $validation);
                }
            }
            $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setWrapText(true);
            $sheet->getStyle('1:1')->getFont()->setBold(true);
        }
        $workbook->setActiveSheetIndex(0);

        return $workbook;
    }
}
