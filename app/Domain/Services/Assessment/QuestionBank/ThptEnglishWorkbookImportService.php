<?php

namespace VistiqCore\Services\Assessment\QuestionBank;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;
use VistiqCore\Models\ExamPart;
use VistiqCore\Models\QuestionDifficulty;
use VistiqCore\Models\QuestionType;

class ThptEnglishWorkbookImportService
{
    private const GUIDE_SHEET = 'HUONG_DAN';

    private const HEADERS = [
        'question_no', 'prompt', 'option_a', 'option_b', 'option_c', 'option_d',
        'correct_option', 'difficulty', 'explanation', 'passage_title', 'passage_body',
    ];

    private const SHARED_TEXT_PARTS = [
        'tieng-anh-part-1', 'tieng-anh-part-2', 'tieng-anh-part-4', 'tieng-anh-part-5', 'tieng-anh-part-6',
    ];

    private const IMAGE_PARTS = ['tieng-anh-part-1', 'tieng-anh-part-2'];

    /** @return array{kind:string,sheets:list<array<string,mixed>>,total_questions:int,can_import:bool} */
    public function preview(string $path): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $workbook = $reader->load($path);
        } catch (Throwable) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể đọc workbook .xlsx. Hãy kiểm tra lại tệp.',
                'errors' => ['file' => ['Workbook không hợp lệ hoặc bị hỏng.']],
            ], 422));
        }

        $worksheets = collect($workbook->getAllSheets())
            ->filter(fn (Worksheet $sheet): bool => Str::upper(trim($sheet->getTitle())) !== self::GUIDE_SHEET)
            ->keyBy(fn (Worksheet $sheet): string => Str::upper(trim($sheet->getTitle())));
        $parts = ExamPart::with(['examType', 'skill'])
            ->whereHas('examType', fn ($query) => $query->where('code', 'thpt'))
            ->get()
            ->keyBy(fn (ExamPart $part): string => Str::lower($part->code));
        $questionTypeId = QuestionType::where('code', 'multiple_choice')->value('id');
        $difficultyIds = QuestionDifficulty::pluck('id', 'code');
        $sheets = [];
        $totalQuestions = 0;
        $allValid = true;

        foreach (ExamGenerationService::THPT_ENGLISH_PARTS as $index => $definition) {
            $partCode = $definition['code'];
            $sheetCode = 'PART_'.($index + 1);
            $worksheet = $worksheets->get($sheetCode);
            $sheet = [
                'code' => $sheetCode,
                'part_code' => $partCode,
                'title' => $this->partTitle($index + 1),
                'expected_count' => (int) $definition['count'],
                'question_count' => 0,
                'questions' => [],
                'errors' => [],
                'blocked' => false,
                'exam_part_id' => $parts->get($partCode)?->id,
                'exam_type' => 'thpt',
                'skill' => 'thpt',
                'exam_part' => $partCode,
                'reading_title' => '',
                'reading_body' => '',
                'requires_group_image' => in_array($partCode, self::IMAGE_PARTS, true),
            ];

            if (! $worksheet) {
                $this->error($sheet, 1, 'Thiếu sheet '.$sheetCode.'. Giữ đủ cả 6 sheet trong mẫu.');
                $sheet['blocked'] = true;
                $allValid = false;
                $sheets[] = $sheet;
                continue;
            }

            if (! $sheet['exam_part_id'] || ! $questionTypeId || $difficultyIds->isEmpty()) {
                $this->error($sheet, 1, 'Danh mục phần thi, loại câu hỏi hoặc độ khó chưa được khởi tạo.');
                $sheet['blocked'] = true;
                $allValid = false;
                $sheets[] = $sheet;
                continue;
            }

            $headerCells = $worksheet->rangeToArray('A1:'.$worksheet->getHighestDataColumn().'1', null, true, true, false)[0] ?? [];
            $headers = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $headerCells);
            if (count($headers) !== count(array_unique(array_filter($headers)))) {
                $this->error($sheet, 1, 'Tiêu đề cột bị trùng.');
                $sheet['blocked'] = true;
            }
            $missingHeaders = array_values(array_diff(self::HEADERS, $headers));
            if ($missingHeaders !== []) {
                $this->error($sheet, 1, 'Thiếu cột bắt buộc: '.implode(', ', $missingHeaders).'.');
                $sheet['blocked'] = true;
            }
            if ($sheet['blocked']) {
                $allValid = false;
                $sheets[] = $sheet;
                continue;
            }

            $lastColumn = $worksheet->getHighestDataColumn();
            $values = $worksheet->rangeToArray('A2:'.$lastColumn.($sheet['expected_count'] + 1), null, true, true, false);
            $extraRows = max(0, $worksheet->getHighestDataRow() - ($sheet['expected_count'] + 1));
            if ($extraRows > 0) {
                for ($rowNumber = $sheet['expected_count'] + 2; $rowNumber <= $worksheet->getHighestDataRow(); $rowNumber++) {
                    $extra = $worksheet->rangeToArray('A'.$rowNumber.':'.$lastColumn.$rowNumber, null, true, true, false)[0] ?? [];
                    if (collect($extra)->contains(fn ($value): bool => trim((string) $value) !== '')) {
                        $this->error($sheet, $rowNumber, 'Phần này chỉ nhận '.$sheet['expected_count'].' câu.');
                    }
                }
            }

            $firstDataRow = 2;
            for ($questionIndex = 0; $questionIndex < $sheet['expected_count']; $questionIndex++) {
                $rowNumber = $firstDataRow + $questionIndex;
                $cells = array_slice($values[$questionIndex] ?? [], 0, count($headers));
                $row = array_combine($headers, array_pad($cells, count($headers), null)) ?: [];
                $rowErrors = [];
                $questionNo = trim((string) ($row['question_no'] ?? ''));
                if ($questionNo !== (string) ($questionIndex + 1)) {
                    $rowErrors[] = 'question_no phải theo thứ tự từ 1 đến '.$sheet['expected_count'].' trong phần.';
                }

                $prompt = trim((string) ($row['prompt'] ?? ''));
                if ($prompt === '') {
                    $rowErrors[] = 'Thiếu prompt.';
                } elseif (Str::length($prompt) > 10000) {
                    $rowErrors[] = 'prompt không được dài quá 10.000 ký tự.';
                }
                $options = [];
                foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $key => $column) {
                    $option = trim((string) ($row[$column] ?? ''));
                    if ($option === '') {
                        $rowErrors[] = 'Thiếu '.$column.'.';
                    } elseif (Str::length($option) > 2000) {
                        $rowErrors[] = $column.' không được dài quá 2.000 ký tự.';
                    } else {
                        $options[$key] = $option;
                    }
                }
                $correctOption = Str::upper(trim((string) ($row['correct_option'] ?? '')));
                if (! in_array($correctOption, ['A', 'B', 'C', 'D'], true)) {
                    $rowErrors[] = 'correct_option phải là A, B, C hoặc D.';
                }
                $difficulty = Str::lower(trim((string) ($row['difficulty'] ?? ''))) ?: 'medium';
                if (! $difficultyIds->has($difficulty)) {
                    $rowErrors[] = 'difficulty chỉ nhận easy, medium hoặc hard.';
                }

                $title = trim((string) ($row['passage_title'] ?? ''));
                $body = trim((string) ($row['passage_body'] ?? ''));
                $explanation = trim((string) ($row['explanation'] ?? ''));
                if (Str::length($explanation) > 10000) {
                    $rowErrors[] = 'explanation không được dài quá 10.000 ký tự.';
                }
                $sharedText = in_array($partCode, self::SHARED_TEXT_PARTS, true);
                if ($sharedText && $questionIndex === 0 && $title === '') {
                    $rowErrors[] = 'Câu đầu tiên cần passage_title cho văn bản dùng chung.';
                }
                if ($sharedText && $questionIndex === 0 && $body === '') {
                    $rowErrors[] = 'Câu đầu tiên cần passage_body cho văn bản dùng chung.';
                }
                if ($sharedText && $title !== '' && Str::length($title) > 255) {
                    $rowErrors[] = 'passage_title không được dài quá 255 ký tự.';
                }
                if ($sharedText && $body !== '' && Str::length($body) > 50000) {
                    $rowErrors[] = 'passage_body không được dài quá 50.000 ký tự.';
                }
                if ($title !== '') {
                    if ($sheet['reading_title'] !== '' && $sheet['reading_title'] !== $title) {
                        $rowErrors[] = 'passage_title phải giống nhau trong cả sheet.';
                    } else {
                        $sheet['reading_title'] = $title;
                    }
                }
                if ($body !== '') {
                    if ($sheet['reading_body'] !== '' && $sheet['reading_body'] !== $body) {
                        $rowErrors[] = 'passage_body phải giống nhau trong cả sheet.';
                    } else {
                        $sheet['reading_body'] = $body;
                    }
                }

                if ($rowErrors !== []) {
                    foreach (array_unique($rowErrors) as $message) {
                        $this->error($sheet, $rowNumber, $message);
                    }
                    continue;
                }

                $sheet['questions'][] = [
                    'row' => $rowNumber,
                    'attributes' => [
                        'exam_part_id' => (int) $sheet['exam_part_id'],
                        'question_type_id' => (int) $questionTypeId,
                        'question_difficulty_id' => (int) $difficultyIds->get($difficulty),
                        'prompt' => $prompt,
                        'explanation' => $explanation,
                        'options' => $options,
                        'correct_options' => [$correctOption],
                        'status' => 'draft',
                        'metadata' => [],
                    ],
                ];
                $sheet['question_count']++;
            }

            if ($sheet['question_count'] !== $sheet['expected_count']) {
                $this->error($sheet, 1, 'Cần đủ '.$sheet['expected_count'].' câu hợp lệ trong phần này.');
            }
            $totalQuestions += $sheet['question_count'];
            if ($sheet['errors'] !== []) {
                $allValid = false;
            }
            $sheets[] = $sheet;
        }

        $unknownSheets = $worksheets->keys()
            ->filter(fn (string $code): bool => ! preg_match('/^PART_[1-6]$/', $code));
        if ($unknownSheets->isNotEmpty()) {
            $allValid = false;
            $sheets[0]['errors'][] = ['row' => 1, 'message' => 'Sheet không được hỗ trợ: '.$unknownSheets->implode(', ').'. Chỉ giữ HUONG_DAN và PART_1 đến PART_6.'];
        }

        $workbook->disconnectWorksheets();

        return [
            'kind' => 'thpt_english',
            'sheets' => $sheets,
            'total_questions' => $totalQuestions,
            'can_import' => $allValid && $totalQuestions === 40 && count($sheets) === 6,
        ];
    }

    public function template(): Spreadsheet
    {
        $workbook = new Spreadsheet();
        $this->addGuideSheet($workbook);

        foreach (ExamGenerationService::THPT_ENGLISH_PARTS as $index => $definition) {
            $this->addQuestionSheet($workbook->createSheet(), 'PART_'.($index + 1), $index + 1, (int) $definition['count']);
        }

        return $workbook;
    }

    public function writer(Spreadsheet $workbook): Xlsx
    {
        return new Xlsx($workbook);
    }

    private function addGuideSheet(Spreadsheet $workbook): void
    {
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle(self::GUIDE_SHEET);
        $sheet->mergeCells('A1:B1');
        $sheet->setCellValue('A1', 'MẪU IMPORT ĐỀ TIẾNG ANH THPT QUỐC GIA · 40 CÂU');
        $rows = [
            ['Nội dung', 'Cách điền'],
            ['Cấu trúc workbook', 'Một file .xlsx gồm 6 sheet PART_1 đến PART_6. Giữ nguyên tên sheet, tiêu đề cột và số dòng câu hỏi. Sheet HUONG_DAN chỉ để đọc.'],
            ['Phân bổ câu', 'PART_1: 6 câu điền từ vào thông báo/quảng cáo; PART_2: 6 câu điền từ vào tờ rơi/văn bản thông tin; PART_3: 5 câu sắp xếp; PART_4: 5 câu điền cụm/câu; PART_5: 8 câu đọc hiểu; PART_6: 10 câu đọc hiểu. Tổng cộng 40 câu.'],
            ['Câu hỏi và đáp án', 'Mỗi dòng là một câu. Điền prompt, đủ option_a đến option_d và correct_option (A/B/C/D). question_no đã được đánh số theo thứ tự trong từng phần.'],
            ['Độ khó', 'difficulty nhận easy, medium hoặc hard. Để trống sẽ dùng medium. explanation không bắt buộc.'],
            ['Văn bản chung', 'Ở PART_1, PART_2, PART_4, PART_5, PART_6, điền passage_title và toàn bộ passage_body tại dòng câu đầu tiên của phần; các dòng sau để trống.'],
            ['Ảnh Phần 1–2', 'Sau khi tải workbook lên và xem trước, chọn một ảnh JPG/PNG/WEBP cho PART_1 và một ảnh cho PART_2. Mỗi ảnh được gắn với các câu trong cùng phần.'],
            ['Import', 'Import chỉ thực hiện khi đủ 6 phần, đủ 40 câu và không còn lỗi. Câu hỏi được lưu ở trạng thái bản nháp.'],
        ];
        $sheet->fromArray($rows, null, 'A2');
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
            $sheet->getRowDimension($row)->setRowHeight(42);
        }
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->freezePane('A3');
    }

    private function addQuestionSheet(Worksheet $sheet, string $title, int $partNumber, int $count): void
    {
        $sheet->setTitle($title);
        $sheet->fromArray(self::HEADERS, null, 'A1');
        for ($questionNo = 1; $questionNo <= $count; $questionNo++) {
            $sheet->setCellValue('A'.($questionNo + 1), $questionNo);
        }
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count(self::HEADERS));
        $lastRow = $count + 1;
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.$lastRow);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()->setFillType('solid')->getStartColor()->setRGB('2457D6');
        $sheet->getStyle('A2:'.$lastColumn.$lastRow)->getAlignment()->setVertical('top')->setWrapText(true);
        $sheet->getColumnDimension('A')->setWidth(14);
        foreach (['B', 'I', 'K'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(42);
        }
        foreach (['C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(24);
        }
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(30);
        for ($row = 2; $row <= $lastRow; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(42);
        }
        $sheet->getComment('A1')->getText()->createTextRun('Câu '.$partNumber.' / thứ tự câu trong phần; không đổi hoặc xóa.');
        $sheet->getComment('J1')->getText()->createTextRun('Điền ở dòng đầu tiên của phần nếu phần này có văn bản chung.');
        $sheet->getComment('K1')->getText()->createTextRun('Văn bản chung của phần; giữ xuống dòng trong cùng một ô.');
        $this->addListValidation($sheet, 'G2:G'.$lastRow, ['A', 'B', 'C', 'D'], false);
        $this->addListValidation($sheet, 'H2:H'.$lastRow, ['easy', 'medium', 'hard'], true);
    }

    /** @param list<string> $options */
    private function addListValidation(Worksheet $sheet, string $range, array $options, bool $allowBlank): void
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank($allowBlank)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setPromptTitle('Chọn giá trị')
            ->setPrompt('Chọn một giá trị trong danh sách.')
            ->setErrorTitle('Giá trị không hợp lệ')
            ->setError('Chọn một giá trị từ danh sách.');
        $validation->setFormula1('"'.implode(',', $options).'"');
        $sheet->setDataValidation($range, $validation);
    }

    private function partTitle(int $part): string
    {
        return match ($part) {
            1 => 'Điền từ · Thông báo / quảng cáo · 6 câu',
            2 => 'Điền từ · Tờ rơi / văn bản thông tin · 6 câu',
            3 => 'Sắp xếp câu / lượt lời · 5 câu',
            4 => 'Điền cụm từ / câu · 5 câu',
            5 => 'Đọc hiểu · 8 câu',
            default => 'Đọc hiểu · 10 câu',
        };
    }

    private function normalizeHeader(string $header): string
    {
        return Str::lower(trim(str_replace([' ', '-'], '_', $header)));
    }

    /** @param array<string,mixed> $sheet */
    private function error(array &$sheet, int $row, string $message): void
    {
        $sheet['errors'][] = ['row' => $row, 'message' => $message];
    }
}
