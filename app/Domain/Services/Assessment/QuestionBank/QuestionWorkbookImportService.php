<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use VistiqCore\Models\ContentRevision;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\LearningStimulus;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;

class QuestionWorkbookImportService
{
    private const GUIDE_SHEET = 'HUONG_DAN';

    private const QUESTION_HEADERS = [
        'exam_type', 'skill', 'exam_part', 'question_type', 'prompt',
        'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'difficulty', 'explanation',
    ];

    /** @return array{kind:string,sheets:list<array<string,mixed>>,total_questions:int} */
    public function preview(string $path, string $kind): array
    {
        abort_unless(in_array($kind, ['listening', 'reading'], true), 422, 'Loại ngân hàng không hợp lệ.');

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $workbook = $reader->load($path);
        } catch (Throwable $exception) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể đọc workbook .xlsx. Hãy kiểm tra lại tệp.',
                'errors' => ['file' => ['Workbook không hợp lệ hoặc bị hỏng.']],
            ], 422));
        }

        $worksheets = array_values(array_filter(
            $workbook->getAllSheets(),
            fn ($sheet): bool => Str::lower(trim($sheet->getTitle())) !== Str::lower(self::GUIDE_SHEET),
        ));
        if ($worksheets === [] || count($worksheets) > 50) {
            $workbook->disconnectWorksheets();
            throw new HttpResponseException(response()->json([
                'message' => 'Workbook cần có từ 1 đến 50 sheet bài học.',
                'errors' => ['file' => ['Workbook cần có từ 1 đến 50 sheet bài học.']],
            ], 422));
        }

        $parts = ExamPart::with(['examType', 'skill'])->get()->groupBy(fn (ExamPart $part) => Str::lower($part->code));
        $questionTypeIds = QuestionType::pluck('id', 'code');
        $difficultyIds = QuestionDifficulty::pluck('id', 'code');
        $usedCodes = $this->existingCodes($kind);
        $listeningCodes = $kind === 'listening'
            ? $this->generatedListeningCodes(count($worksheets), $usedCodes)
            : [];
        $sheetCodeCounts = $kind === 'reading'
            ? collect($worksheets)->countBy(fn ($sheet) => Str::lower(trim($sheet->getTitle())))
            : collect();
        $totalQuestions = 0;
        $sheets = [];

        foreach ($worksheets as $index => $worksheet) {
            $sourceSheet = trim($worksheet->getTitle());
            $code = $kind === 'listening' ? $listeningCodes[$index] : $sourceSheet;
            $sheet = [
                'code' => $code,
                'source_sheet' => $sourceSheet,
                'question_count' => 0,
                'questions' => [],
                'errors' => [],
                'blocked' => false,
                'reading_title' => '',
                'reading_body' => '',
                'exam_part_id' => null,
                'exam_type' => null,
                'skill' => null,
                'exam_part' => null,
            ];

            if ($kind === 'reading') {
                if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,30}$/', $code)) {
                    $this->error($sheet, 1, 'Tên sheet phải là mã bài gồm tối đa 31 ký tự chữ, số, gạch dưới hoặc gạch ngang.');
                    $sheet['blocked'] = true;
                }
                if (($sheetCodeCounts[Str::lower($code)] ?? 0) > 1) {
                    $this->error($sheet, 1, 'Mã sheet bị trùng trong workbook.');
                    $sheet['blocked'] = true;
                }
                if (isset($usedCodes[Str::lower($code)])) {
                    $this->error($sheet, 1, 'Mã bài đã tồn tại trong ngân hàng '.$kind.'.');
                    $sheet['blocked'] = true;
                }
            }

            $lastColumn = $worksheet->getHighestDataColumn();
            $lastRow = $worksheet->getHighestDataRow();
            $values = $lastRow > 0 ? $worksheet->rangeToArray('A1:'.$lastColumn.$lastRow, null, true, true, false) : [];
            $headerCells = array_shift($values) ?? [];
            $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $headerCells);
            $headers = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));
            if (count($headers) !== count(array_unique($headers))) {
                $this->error($sheet, 1, 'Tiêu đề cột bị trùng.');
                $sheet['blocked'] = true;
            }
            $requiredHeaders = self::QUESTION_HEADERS;
            $missingHeaders = array_values(array_diff($requiredHeaders, $headers));
            if ($missingHeaders !== []) {
                $this->error($sheet, 1, 'Thiếu cột bắt buộc: '.implode(', ', $missingHeaders).'.');
                $sheet['blocked'] = true;
            }

            if ($sheet['blocked']) {
                $sheets[] = $sheet;

                continue;
            }

            $firstDataRow = null;
            $partId = null;
            $partKey = null;
            foreach ($values as $index => $cells) {
                $rowNumber = $index + 2;
                $cells = array_slice($cells, 0, count($headers));
                $row = array_combine($headers, array_pad($cells, count($headers), null));
                if (! $row || collect($row)->every(fn ($value) => trim((string) $value) === '')) {
                    continue;
                }
                $firstDataRow ??= $rowNumber;
                if (($totalQuestions + $sheet['question_count']) >= 2000) {
                    $this->error($sheet, $rowNumber, 'Mỗi workbook tối đa 2.000 câu hỏi.');
                    break;
                }

                $rowErrors = [];
                $examCode = Str::lower(trim((string) ($row['exam_type'] ?? '')));
                $skillCode = Str::lower(trim((string) ($row['skill'] ?? '')));
                $partCode = Str::lower(trim((string) ($row['exam_part'] ?? '')));
                $ieltsSection = null;
                if ($examCode === 'ielts' && $kind === 'listening') {
                    $sectionCode = preg_replace('/[^a-z0-9]+/', '', Str::ascii($partCode));
                    if ($sectionCode === 'ieltslistening') {
                        $partCode = 'listening';
                    } elseif (preg_match('/^(?:ielts)?(?:listening)?(?:part|section)([1-4])$/', $sectionCode, $matches)) {
                        $ieltsSection = (int) $matches[1];
                        $partCode = 'listening-part-'.$ieltsSection;
                    }
                }
                $candidates = $parts->get($partCode, collect());
                $part = $candidates->first(fn (ExamPart $candidate) => $candidate->examType?->code === $examCode && $candidate->skill?->code === $kind)
                    ?? $candidates->first(fn (ExamPart $candidate) => $candidate->skill?->code === $kind)
                    ?? $candidates->first();
                $resolvedPartKey = $part ? (string) $part->id.':'.($ieltsSection ?? 0) : null;
                if (! $part || $part->skill?->code !== $kind) {
                    $rowErrors[] = 'exam_part không tồn tại hoặc không thuộc kỹ năng '.$kind.'.';
                } elseif ($partId !== null && $partKey !== $resolvedPartKey) {
                    $rowErrors[] = 'Một sheet chỉ được chứa câu hỏi thuộc cùng một exam_part.';
                }
                if ($examCode === '') {
                    $rowErrors[] = 'Thiếu exam_type (toeic hoặc ielts).';
                } elseif ($part && $part->examType?->code !== $examCode) {
                    $rowErrors[] = 'exam_type không khớp với exam_part.';
                }
                if ($skillCode !== $kind) {
                    $rowErrors[] = 'skill phải là '.$kind.' trong workbook này.';
                } elseif ($part && $part->skill?->code !== $skillCode) {
                    $rowErrors[] = 'skill không khớp với exam_part.';
                }
                $questionTypeCode = Str::lower(trim((string) ($row['question_type'] ?? '')));
                if ($questionTypeCode === '') {
                    $rowErrors[] = 'Thiếu question_type.';
                } elseif ($questionTypeCode !== 'multiple_choice') {
                    $rowErrors[] = 'question_type hiện chỉ nhận multiple_choice vì mẫu dùng bốn lựa chọn và một đáp án đúng.';
                } elseif (! $questionTypeIds->has($questionTypeCode)) {
                    $rowErrors[] = 'question_type chưa được khởi tạo trong danh mục.';
                }

                $prompt = trim((string) ($row['prompt'] ?? ''));
                if ($prompt === '') {
                    $rowErrors[] = 'Thiếu prompt.';
                }
                $options = [];
                foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $key => $column) {
                    $option = trim((string) ($row[$column] ?? ''));
                    if ($option === '') {
                        $rowErrors[] = 'Thiếu '.$column.'.';
                    } else {
                        $options[$key] = $option;
                    }
                }
                $correctOption = strtoupper(trim((string) ($row['correct_option'] ?? '')));
                if (! in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
                    $rowErrors[] = 'correct_option phải là A, B, C hoặc D.';
                }

                $difficulty = Str::lower(trim((string) ($row['difficulty'] ?? ''))) ?: 'medium';
                if (! $difficultyIds->has($difficulty)) {
                    $rowErrors[] = 'difficulty chỉ nhận easy, medium hoặc hard.';
                }

                $readingTitle = trim((string) ($row['reading_title'] ?? ''));
                $readingBody = trim((string) ($row['reading_body'] ?? ''));
                if ($kind === 'reading') {
                    if ($readingTitle !== '') {
                        if ($sheet['reading_title'] !== '' && $sheet['reading_title'] !== $readingTitle) {
                            $rowErrors[] = 'reading_title phải thống nhất trong cùng sheet.';
                        } else {
                            $sheet['reading_title'] = $readingTitle;
                        }
                    }
                    if ($readingBody !== '') {
                        if ($sheet['reading_body'] !== '' && $sheet['reading_body'] !== $readingBody) {
                            $rowErrors[] = 'reading_body phải thống nhất trong cùng sheet.';
                        } else {
                            $sheet['reading_body'] = $readingBody;
                        }
                    }
                }

                if ($rowErrors !== []) {
                    foreach (array_unique($rowErrors) as $message) {
                        $this->error($sheet, $rowNumber, $message);
                    }

                    continue;
                }

                $partId = (int) $part->id;
                $partKey = $resolvedPartKey;
                $sheet['exam_part_id'] = $partId;
                $sheet['exam_type'] = $examCode;
                $sheet['skill'] = $skillCode;
                $sheet['exam_part'] = $partCode;
                $sheet['questions'][] = [
                    'row' => $rowNumber,
                    'attributes' => [
                        'exam_part_id' => $partId,
                        'question_type_id' => (int) $questionTypeIds->get($questionTypeCode),
                        'question_difficulty_id' => (int) $difficultyIds->get($difficulty),
                        'prompt' => $prompt,
                        'explanation' => trim((string) ($row['explanation'] ?? '')),
                        'options' => $options,
                        'correct_options' => [$correctOption],
                        'status' => 'draft',
                        'metadata' => $ieltsSection === null ? [] : ['ielts_section' => $ieltsSection],
                    ],
                ];
                $sheet['question_count']++;
            }

            if ($firstDataRow === null) {
                $this->error($sheet, 2, 'Sheet chưa có dòng câu hỏi.');
            }
            if ($sheet['question_count'] === 0 && $firstDataRow !== null) {
                $this->error($sheet, $firstDataRow, 'Sheet không có câu hỏi hợp lệ để import.');
            }
            if ($sheet['question_count'] > 0 && ($questionTypeIds->isEmpty() || $difficultyIds->isEmpty())) {
                $this->error($sheet, 1, 'Danh mục loại câu hỏi hoặc độ khó chưa được khởi tạo.');
                $sheet['questions'] = [];
                $sheet['question_count'] = 0;
            }

            $totalQuestions += $sheet['question_count'];
            $sheets[] = $sheet;
        }

        $workbook->disconnectWorksheets();

        return ['kind' => $kind, 'sheets' => $sheets, 'total_questions' => $totalQuestions];
    }

    public function template(string $kind): Spreadsheet
    {
        abort_unless(in_array($kind, ['listening', 'reading'], true), 404);
        $workbook = new Spreadsheet;
        $this->addGuideSheet($workbook, $kind);
        $headers = [...self::QUESTION_HEADERS, ...($kind === 'reading' ? ['reading_title', 'reading_body'] : [])];
        $this->addQuestionSheet($workbook->createSheet(), strtoupper($kind).'_001', $headers, $kind);
        $this->addQuestionSheet($workbook->createSheet(), strtoupper($kind).'_002', $headers, $kind);

        return $workbook;
    }

    private function addGuideSheet(Spreadsheet $workbook, string $kind): void
    {
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle(self::GUIDE_SHEET);
        $label = $kind === 'listening' ? 'LISTENING' : 'READING';
        $skill = $kind;
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', 'HƯỚNG DẪN IMPORT CÂU HỎI '.$label);
        $instructions = [
            ['Mục', 'Hướng dẫn chi tiết'],
            ['Sheet dữ liệu', $kind === 'listening'
                ? 'Mỗi sheet dữ liệu là một bài Listening. Tên sheet chỉ dùng để nhận diện tab; hệ thống tự tạo mã theo giờ Việt Nam. Không dùng tên HUONG_DAN.'
                : 'Mỗi sheet dữ liệu là một bài Reading. Đổi tên READING_001 / READING_002 thành mã bài riêng; tối đa 31 ký tự chữ, số, dấu gạch dưới hoặc gạch ngang. Không dùng tên HUONG_DAN.'],
            ['Mã bài', $kind === 'listening'
                ? 'Mã Listening được hệ thống tự sinh theo giờ Việt Nam, dạng LISTENING_YYYYMMDD_HHMMSS_01 (năm, tháng, ngày, giờ, số thứ tự sheet). Mỗi sheet chỉ chứa câu hỏi của một phần thi (exam_part).'
                : 'Mã bài phải duy nhất trong workbook và chưa tồn tại trong ngân hàng. Mỗi sheet chỉ chứa câu hỏi của một phần thi (exam_part).'],
            ['Dòng tiêu đề', 'Giữ nguyên các tên cột ở dòng 1. Không xóa cột bắt buộc và không gộp ô trong sheet dữ liệu. Mỗi dòng từ dòng 2 trở đi là một câu hỏi.'],
            ['Phân loại', 'Mỗi dòng cần có exam_type (toeic hoặc ielts), skill ('.$skill.'), exam_part (mã part phù hợp) và question_type=multiple_choice. exam_type và skill phải khớp với exam_part.'],
            ['Câu hỏi', 'prompt là nội dung câu hỏi; option_a, option_b, option_c, option_d đều bắt buộc. Mẫu này hỗ trợ trắc nghiệm một đáp án; chọn loại khác sẽ bị báo lỗi khi xem trước.'],
            ['Đáp án', 'correct_option chỉ nhận một giá trị A, B, C hoặc D. difficulty nhận easy, medium hoặc hard; để trống sẽ dùng medium. explanation không bắt buộc.'],
        ];
        if ($kind === 'listening') {
            $instructions[] = ['IELTS Listening', 'Với IELTS, dùng listening-part-1 đến listening-part-4 (có thể ghi part-1 đến part-4 hoặc section-1 đến section-4). Mã listening chỉ dành cho bài không tách Part.'];
            $instructions[] = ['Audio', 'Sau khi xem trước workbook, đính kèm một file audio cho từng sheet có câu hợp lệ. Hỗ trợ MP3, WAV, M4A, OGG; giới hạn dung lượng theo cấu hình Tài nguyên của hệ thống.'];
            $instructions[] = ['Ảnh TOEIC Part 1', 'Nếu exam_type=toeic, skill=listening và exam_part=part-1, bước xác nhận sẽ yêu cầu một ảnh JPG, PNG hoặc WEBP cho mỗi câu hợp lệ.'];
            $instructions[] = ['Xác nhận', 'Chọn Xem trước workbook để kiểm tra số câu và lỗi theo sheet/dòng. Sau đó gán audio đúng mã bài và xác nhận import. Câu hợp lệ được lưu ở trạng thái bản nháp.'];
        } else {
            $instructions[] = ['Bài đọc', 'Nếu workbook có cột reading_title và reading_body, điền tiêu đề và nội dung ở dòng câu hỏi đầu tiên. Nếu chưa có, hãy bổ sung bài đọc tại bước xem trước workbook.'];
            $instructions[] = ['Xác nhận', 'Chọn Xem trước workbook để kiểm tra câu hỏi, xem hoặc bổ sung nội dung từng bài đọc, sau đó xác nhận import. Các câu hợp lệ được liên kết với bài đọc cùng mã và lưu ở trạng thái bản nháp.'];
        }
        $instructions[] = ['Giới hạn', 'Tối đa 50 sheet bài học và 2.000 câu trong một workbook. Sheet HUONG_DAN chỉ để hướng dẫn và được hệ thống tự bỏ qua khi import.'];
        $sheet->fromArray($instructions, null, 'A2');
        $lastRow = $sheet->getHighestRow();
        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(112);
        $sheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:B1')->getFill()->setFillType('solid')->getStartColor()->setRGB('2457D6');
        $sheet->getStyle('A2:B2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A2:B2')->getFill()->setFillType('solid')->getStartColor()->setRGB('173B88');
        $sheet->getStyle('A1:B'.$lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
        $sheet->getStyle('A3:A'.$lastRow)->getFont()->setBold(true);
        for ($row = 3; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(48);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->freezePane('A3');
    }

    /** @param list<string> $headers */
    private function addQuestionSheet(Worksheet $sheet, string $title, array $headers, string $skill): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray($headers, null, 'A1');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1000');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('2457D6');
        $sheet->getRowDimension(1)->setRowHeight(30);
        $columnByHeader = [];
        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $columnByHeader[$header] = $column;
            $sheet->getColumnDimension($column)->setWidth(match ($header) {
                'prompt', 'explanation', 'reading_body' => 44,
                'reading_title' => 28,
                default => 18,
            });
        }
        $sheet->getStyle('A2:'.$lastColumn.'1000')->getAlignment()->setVertical('top')->setWrapText(true);
        $comments = [
            'exam_type' => 'Mã kỳ thi: toeic hoặc ielts. Phải khớp với exam_part.',
            'skill' => 'Kỹ năng của workbook này: '.$skill.'.',
            'exam_part' => 'Mã part thuộc đúng kỳ thi và kỹ năng, ví dụ TOEIC listening: part-1 đến part-4; IELTS listening: listening-part-1 đến listening-part-4, hoặc listening cho bài không tách Part.',
            'question_type' => 'Mẫu hiện hỗ trợ multiple_choice: bốn lựa chọn và một đáp án đúng.',
            'prompt' => 'Nội dung câu hỏi. Mỗi dòng từ dòng 2 là một câu.',
            'option_a' => 'Nội dung lựa chọn A; bắt buộc nhập đủ A, B, C, D.',
            'option_b' => 'Nội dung lựa chọn B; bắt buộc nhập đủ A, B, C, D.',
            'option_c' => 'Nội dung lựa chọn C; bắt buộc nhập đủ A, B, C, D.',
            'option_d' => 'Nội dung lựa chọn D; bắt buộc nhập đủ A, B, C, D.',
            'correct_option' => 'Chỉ ghi một chữ cái: A, B, C hoặc D.',
            'difficulty' => 'easy, medium hoặc hard; bỏ trống sẽ dùng medium.',
            'explanation' => 'Không bắt buộc.',
            'reading_title' => 'Có thể nhập ở dòng câu hỏi đầu tiên hoặc bổ sung tại bước xem trước workbook.',
            'reading_body' => 'Có thể nhập ở dòng câu hỏi đầu tiên hoặc bổ sung tại bước xem trước workbook.',
        ];
        foreach ($comments as $header => $comment) {
            if (isset($columnByHeader[$header])) {
                $sheet->getComment($columnByHeader[$header].'1')->getText()->createTextRun($comment);
            }
        }
        $parts = ExamPart::with(['examType', 'skill'])->get()
            ->filter(fn (ExamPart $part): bool => $part->skill?->code === $skill && in_array($part->examType?->code, ['toeic', 'ielts'], true));
        $lists = [
            'exam_type' => $parts->map(fn (ExamPart $part): string => (string) $part->examType->code)->unique()->values()->all(),
            'skill' => [$skill],
            'exam_part' => $parts->map(fn (ExamPart $part): string => $part->code)->unique()->values()->all(),
            'question_type' => ['multiple_choice'],
            'correct_option' => ['A', 'B', 'C', 'D'],
            'difficulty' => ['easy', 'medium', 'hard'],
        ];
        foreach ($lists as $header => $values) {
            if (! isset($columnByHeader[$header]) || $values === []) {
                continue;
            }
            $validation = new DataValidation;
            $validation->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank($header === 'difficulty')
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setPromptTitle('Chọn giá trị phân loại')
                ->setPrompt('Chọn một mã trong danh sách.')
                ->setErrorTitle('Giá trị không hợp lệ')
                ->setError('Hãy chọn giá trị từ danh sách có sẵn.');
            $validation->setFormula1('"'.implode(',', $values).'"');
            $column = $columnByHeader[$header];
            $sheet->setDataValidation($column.'2:'.$column.'1000', $validation);
        }
    }

    public function writer(Spreadsheet $workbook): Xlsx
    {
        return new Xlsx($workbook);
    }

    /**
     * @param array<string,true> $usedCodes
     * @return list<string>
     */
    private function generatedListeningCodes(int $count, array $usedCodes): array
    {
        $createdAt = now('Asia/Ho_Chi_Minh');
        $secondsOffset = 0;

        do {
            $timestamp = $createdAt->copy()->addSeconds($secondsOffset)->format('Ymd_His');
            $codes = [];
            for ($index = 1; $index <= $count; $index++) {
                $codes[] = sprintf('LISTENING_%s_%02d', $timestamp, $index);
            }

            $hasConflict = false;
            foreach ($codes as $code) {
                if (isset($usedCodes[Str::lower($code)])) {
                    $hasConflict = true;

                    break;
                }
            }
            $secondsOffset++;
        } while ($hasConflict);

        return $codes;
    }

    public function codeExists(string $kind, string $code): bool
    {
        return isset($this->existingCodes($kind)[Str::lower(trim($code))]);
    }

    /** @return array<string,true> */
    private function existingCodes(string $kind): array
    {
        $codes = [];
        if ($kind === 'listening') {
            foreach (DB::table('question_groups')->get(['metadata']) as $group) {
                $metadata = json_decode((string) $group->metadata, true) ?: [];
                $code = Str::lower(trim((string) ($metadata['lesson_code'] ?? '')));
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        } else {
            $stimuli = LearningStimulus::where('stimulus_type', 'reading')->get(['id']);
            foreach (ContentRevision::where('kind', 'stimulus')->whereIn('entity_id', $stimuli->pluck('id'))->get(['payload']) as $revision) {
                $metadata = $revision->payload['metadata'] ?? [];
                $code = Str::lower(trim((string) ($metadata['lesson_code'] ?? '')));
                if ($code !== '') {
                    $codes[$code] = true;
                }
            }
        }

        return $codes;
    }

    private function normalizeHeader(string $header): string
    {
        $header = Str::slug(Str::ascii(trim($header)), '_');

        return match ($header) {
            'part', 'exam_part_code' => 'exam_part',
            'exam', 'exam_code', 'exam_type_code' => 'exam_type',
            'skill_code' => 'skill',
            'type', 'question_type_code' => 'question_type',
            'question', 'content' => 'prompt',
            'answer', 'correct_answer' => 'correct_option',
            'option1', 'choice_a' => 'option_a',
            'option2', 'choice_b' => 'option_b',
            'option3', 'choice_c' => 'option_c',
            'option4', 'choice_d' => 'option_d',
            'explain' => 'explanation',
            default => $header,
        };
    }

    /** @param array<string,mixed> $sheet */
    private function error(array &$sheet, int $row, string $message): void
    {
        $sheet['errors'][] = ['row' => $row, 'message' => $message];
    }
}
