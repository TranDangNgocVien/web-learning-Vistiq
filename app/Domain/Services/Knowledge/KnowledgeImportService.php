<?php

namespace VistiqCore\Services\Knowledge;

use Illuminate\Http\UploadedFile;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use VistiqCore\Models\LearningItem;
use VistiqCore\Services\Assessment\ContentApproval\ContentRevisionService;
use VistiqCore\Services\System\AuditService;

class KnowledgeImportService
{
    public const MAX_ROWS = 1000;

    public function __construct(private readonly ContentRevisionService $revisions, private readonly KnowledgeTaxonomyService $taxonomy) {}

    public function preview(UploadedFile $file, string $type): array
    {
        $records = $this->parseFile($file);
        $headers = array_map(fn ($v) => Str::snake(mb_strtolower(trim((string) $v))), array_shift($records) ?? []);
        $errors = [];
        $parsed = [];
        if (! in_array('english', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            $errors[] = ['row' => 1, 'message' => 'Cần cột english và tên các cột không được trùng.'];
        }
        if ($records === []) {
            $errors[] = ['row' => 2, 'message' => 'Tệp không có dòng dữ liệu.'];
        }
        foreach ($records as $index => $row) {
            $data = [];
            foreach ($headers as $i => $header) {
                $key = match ($header) {
                    'vietnamese', 'nghia' => 'meaning_vi', 'definition' => 'definition_en', 'pronunciation' => 'ipa', default => $header
                };
                $data[$key] = trim((string) ($row[$i] ?? ''));
            }
            if (count($row) !== count($headers)) {
                $errors[] = ['row' => $index + 2, 'message' => 'Số cột không khớp dòng tiêu đề.'];
            }
            $validator = Validator::make([...$data, 'type' => $type], KnowledgeInput::rules());
            if ($validator->fails()) {
                $errors[] = ['row' => $index + 2, 'message' => implode(' ', $validator->errors()->all())];
            }
            $parsed[] = $validator->fails() ? $data : $validator->validated();
        }

        return ['headers' => $headers, 'rows' => $parsed, 'total' => count($records), 'errors' => $errors];
    }

    public function import(array $rows, string $type, string $duplicatePolicy = 'skip'): array
    {
        Validator::make(['type' => $type, 'duplicate_policy' => $duplicatePolicy, 'rows' => $rows], [
            'type' => 'required|in:vocabulary,collocation,phrasal_verb,idiom,grammar', 'duplicate_policy' => 'required|in:skip,overwrite', 'rows' => 'required|array|min:1|max:'.self::MAX_ROWS,
        ])->validate();
        $validated = [];
        $errors = [];
        foreach ($rows as $index => $row) {
            $validator = Validator::make(is_array($row) ? [...$row, 'type' => $type] : [], KnowledgeInput::rules());
            if ($validator->fails()) {
                $errors['rows.'.($index + 2)] = ['Dòng '.($index + 2).': '.implode(' ', $validator->errors()->all())];
            } else {
                $validated[] = $validator->validated();
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($validated, $type, $duplicatePolicy): array {
            $imported = 0;
            $skipped = 0;
            $typeId = DB::table('learning_item_types')->where('code', $type)->value('id');
            abort_unless($typeId, 422, 'Loại kiến thức không còn tồn tại.');
            foreach ($validated as $data) {
                $english = trim($data['english']);
                $existing = LearningItem::where('learning_item_type_id', $typeId)->whereRaw('LOWER(canonical_text) = ?', [mb_strtolower($english)])->lockForUpdate()->first();
                if ($existing && $duplicatePolicy === 'skip') {
                    $skipped++;

                    continue;
                }
                if ($existing) {
                    $this->revisions->save('knowledge', $existing, $data, $existing->revision_counter ?? 0);
                } else {
                    $item = LearningItem::create(['learning_item_type_id' => $typeId, 'canonical_text' => $english, 'slug' => Str::slug($english).'-'.Str::random(12), 'created_by' => auth()->id(), 'status' => 'draft']);
                    $this->revisions->save('knowledge', $item, $data, 0);
                }
                $imported++;
            }
            app(AuditService::class)->record('knowledge.import', 'knowledge', null, [], ['type' => $type, 'imported' => $imported, 'duplicate_skipped' => $skipped]);

            return ['imported' => $imported, 'skipped' => $skipped, 'errors' => []];
        });
    }

    public function exportCsv($query): string
    {
        $typeLabels = $this->taxonomy->types()->pluck('label', 'id');
        $levelLabels = $this->taxonomy->levels()->pluck('label', 'id');

        $headers = ['ID', 'English', 'Type', 'Level', 'Status', 'Updated'];
        $output = fopen('php://temp', 'r+');
        // BOM for Excel UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        $query->chunk(500, function ($items) use ($output, $typeLabels, $levelLabels): void {
            foreach ($items as $item) {
                fputcsv($output, [
                    $item->id,
                    $this->sanitizeCsvField($item->canonical_text),
                    $this->sanitizeCsvField($typeLabels[$item->learning_item_type_id] ?? ''),
                    $this->sanitizeCsvField($levelLabels[$item->proficiency_level_id] ?? ''),
                    $item->status,
                    $item->updated_at?->toDateTimeString() ?? '',
                ]);
            }
        });

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Sanitize field to prevent CSV formula injection.
     */
    private function sanitizeCsvField(string $value): string
    {
        if (in_array(substr($value, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    private function parseFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        if ($content === false || str_contains($content, "\0")) {
            throw ValidationException::withMessages(['file' => 'Không thể đọc tệp CSV/TSV văn bản.']);
        }
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $firstLine = fgets($handle) ?: '';
        $delimiter = str_contains($firstLine, "\t") ? "\t" : ',';
        rewind($handle);
        $rows = [];
        try {
            while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }
                $rows[] = $row;
                if (count($rows) > self::MAX_ROWS + 1) {
                    throw ValidationException::withMessages(['file' => 'Mỗi lần import tối đa '.self::MAX_ROWS.' dòng. Hãy chia nhỏ tệp.']);
                }
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }
}
