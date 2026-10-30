<?php

namespace VistiqAdmin\Services\Admin\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use VistiqCore\Services\Shared\CompactDatabase as DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;
use VistiqCore\Services\System\AuditService;

/** Private files deliberately survive restoration of the database and its cache/session tables. */
class DatabaseOperationsService
{
    public function __construct(private DatabaseBackupService $backup, private AuditService $audit) {}

    private function path(string $id, string $extension = 'json'): string
    {
        abort_unless(Str::isUuid($id), 404);

        return 'database-operations/'.$id.'.'.$extension;
    }

    private function write(array $operation): void
    {
        $disk = Storage::disk('local');
        $path = $this->path($operation['id']);
        $temporary = $path.'.'.Str::random(12).'.tmp';
        if (! $disk->put($temporary, json_encode($operation, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)) || ! rename($disk->path($temporary), $disk->path($path))) {
            throw new RuntimeException('Không thể lưu trạng thái vận hành.');
        }
    }

    public function operation(string $id): array
    {
        $json = Storage::disk('local')->get($this->path($id));
        abort_unless($json, 404);

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    public function listing(int $page = 1): LengthAwarePaginator
    {
        $items = [];
        foreach (Storage::disk('local')->files('database-operations') as $file) {
            if (! str_ends_with($file, '.json')) {
                continue;
            }
            $item = json_decode(Storage::disk('local')->get($file), true);
            if (is_array($item)) {
                $items[] = $item;
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        $page = max(1, $page);

        return new LengthAwarePaginator(array_slice($items, ($page - 1) * 20, 20), count($items), 20, $page, ['path' => request()->url(), 'query' => request()->except('page')]);
    }

    private function allowLongRunningOperation(): void
    {
        $executionLimit = (int) ini_get('max_execution_time');
        if ($executionLimit > 0 && $executionLimit < 300) {
            set_time_limit(300);
        }
    }

    public function createBackup(int $actor, bool $schema = true, bool $data = true, string $kind = 'backup'): array
    {
        if (! $schema && ! $data) {
            throw ValidationException::withMessages(['backup' => 'Chọn cấu trúc, dữ liệu hoặc cả hai.']);
        }
        $this->allowLongRunningOperation();
        $id = (string) Str::uuid();
        $disk = Storage::disk('local');
        $disk->makeDirectory('database-operations');
        $stream = fopen($disk->path($this->path($id, 'sql')), 'xb');
        if (! $stream) {
            throw new RuntimeException('Không thể tạo file sao lưu.');
        }
        try {
            $metadata = DB::transaction(fn () => $this->backup->export($schema, $data, $stream));
        } catch (Throwable $exception) {
            fclose($stream);
            $stream = null;
            $disk->delete($this->path($id, 'sql'));
            throw $exception;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
        $operation = ['id' => $id, 'actor_id' => $actor, 'kind' => $kind, 'state' => 'available', 'filename' => 'vistiq-'.$kind.'-'.now()->format('Ymd-His').'.sql', 'created_at' => now()->toIso8601String(), 'include_schema' => $schema, 'include_data' => $data];
        try {
            $absolutePath = $disk->path($this->path($id, 'sql'));
            $size = filesize($absolutePath);
            if ($size === false) {
                throw new RuntimeException('Không thể xác định dung lượng file sao lưu.');
            }
            $checksum = hash_file('sha256', $absolutePath);
            if ($checksum === false) {
                throw new RuntimeException('Không thể kiểm tra integrity file sao lưu.');
            }
            $operation = [...$operation, ...$metadata, 'size' => $size, 'checksum' => $checksum];
            $this->write($operation);
        } catch (Throwable $exception) {
            $disk->delete($this->path($id, 'sql'));
            throw $exception;
        }
        $this->audit->record('database.backup.create', 'database', null, [], ['artifact_id' => $id, 'kind' => $kind, 'checksum' => $operation['checksum']]);

        return $operation;
    }

    private function inspect(string $id): array
    {
        $disk = Storage::disk('local');
        $path = $this->path($id, 'sql');
        $absolutePath = $disk->path($path);
        $size = is_file($absolutePath) ? filesize($absolutePath) : false;
        if ($size === false) {
            throw new RuntimeException('Không tìm thấy file sao lưu.');
        }
        $checksum = hash_file('sha256', $absolutePath);
        if (! is_string($checksum)) {
            throw new RuntimeException('Không thể kiểm tra integrity file sao lưu.');
        }

        return [...$this->backup->preflightFile($absolutePath), 'size' => $size, 'checksum' => $checksum];
    }

    public function preflight(UploadedFile $file, int $actor): array
    {
        $this->allowLongRunningOperation();
        $id = (string) Str::uuid();
        $disk = Storage::disk('local');
        $file->storeAs('database-operations', $id.'.sql', 'local');
        try {
            $operation = ['id' => $id, 'actor_id' => $actor, 'kind' => 'restore', 'state' => 'ready', 'filename' => basename($file->getClientOriginalName()), 'created_at' => now()->toIso8601String(), 'expires_at' => now()->addMinutes(20)->toIso8601String(), ...$this->inspect($id)];
            $this->write($operation);
            $this->audit->record('database.restore.preflight', 'database', null, [], ['operation_id' => $id, 'checksum' => $operation['checksum']]);

            return $operation;
        } catch (Throwable $exception) {
            $disk->delete($this->path($id, 'sql'));
            $this->audit->record('database.restore.preflight', 'database', null, [], ['reason' => 'invalid_backup'], 'failure');
            throw ValidationException::withMessages(['file' => 'Bản sao lưu không hợp lệ, không cùng loại database, bị cắt ngắn hoặc chứa câu lệnh không hỗ trợ. Hãy xuất lại từ Vistiq.']);
        }
    }

    public function verifiedPath(string $id): string
    {
        $operation = $this->operation($id);
        abort_unless(in_array($operation['kind'], ['backup', 'recovery']) && $operation['state'] === 'available', 404);
        $path = Storage::disk('local')->path($this->path($id, 'sql'));
        $size = is_file($path) ? filesize($path) : false;
        if ($size === false) {
            throw ValidationException::withMessages(['backup' => 'Không tìm thấy file sao lưu.']);
        }
        $checksum = hash_file('sha256', $path);
        if (! is_string($checksum) || ! hash_equals($operation['checksum'], $checksum)) {
            throw ValidationException::withMessages(['backup' => 'Integrity không hợp lệ. File đã thay đổi; hãy tạo bản sao lưu mới.']);
        }

        return $path;
    }

    public function restore(string $id, int $actor): array
    {
        $this->allowLongRunningOperation();
        $disk = Storage::disk('local');
        $disk->makeDirectory('database-operations');
        $lock = fopen($disk->path('database-operations/restore.lock'), 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) {
                fclose($lock);
            }
            throw ValidationException::withMessages(['restore' => 'Đang có thao tác phục hồi khác. Hãy chờ và kiểm tra trạng thái.']);
        }
        try {
            $operation = $this->operation($id);
            abort_unless($operation['kind'] === 'restore' && $operation['actor_id'] === $actor, 404);
            if ($operation['state'] !== 'ready' || now()->gte($operation['expires_at'])) {
                throw ValidationException::withMessages(['restore' => 'Phiếu xác nhận đã dùng hoặc hết hạn. Hãy kiểm tra file lại.']);
            }
            $inspection = $this->inspect($id);
            if (! hash_equals($operation['checksum'], $inspection['checksum'])) {
                throw ValidationException::withMessages(['restore' => 'File đã thay đổi sau bước kiểm tra. Hãy tải lên và kiểm tra lại.']);
            }
            $operation['state'] = 'running';
            $operation['started_at'] = now()->toIso8601String();
            $this->write($operation);
            $maintenance = false;
            $recovery = null;
            $safe = true;
            try {
                $this->audit->record('database.restore.request', 'database', null, [], ['operation_id' => $id], 'pending');
                $maintenance = true;
                if (Artisan::call('down', ['--retry' => 60]) !== 0) {
                    throw new RuntimeException('Không thể bật bảo trì.');
                }
                $recovery = $this->createBackup($actor, true, true, 'recovery');
                $operation['recovery_id'] = $recovery['id'];
                $this->write($operation);
                $safe = false;
                $this->backup->restoreFile($disk->path($this->path($id, 'sql')));
                $this->verifyRelations();
                $this->invalidateSessions();
                $safe = true;
                $operation['state'] = 'succeeded';
                $operation['message'] = 'Đã phục hồi và thu hồi các phiên cũ. Đăng nhập lại để tiếp tục.';
            } catch (Throwable $exception) {
                report($exception);
                $operation['state'] = 'failed';
                if (! $safe && $recovery) {
                    try {
                        $this->backup->restoreFile($this->verifiedPath($recovery['id']));
                        $this->verifyRelations();
                        $safe = true;
                        $operation['recovered'] = true;
                    } catch (Throwable $recoveryError) {
                        report($recoveryError);
                    }
                }
                $operation['message'] = $safe ? 'Phục hồi thất bại. Dữ liệu trước thao tác đã được giữ hoặc khôi phục; kiểm tra file rồi thực hiện preflight mới.' : 'Phục hồi và recovery thất bại. Ứng dụng tiếp tục bảo trì; quản trị máy chủ cần phục hồi từ recovery backup trong private storage.';
            } finally {
                $operation['finished_at'] = now()->toIso8601String();
                $operation['maintenance_retained'] = ! $safe;
                try {
                    $this->write($operation);
                    try {
                        $this->audit->record('database.restore.result', 'database', null, [], ['operation_id' => $id, 'state' => $operation['state'], 'recovered' => $operation['recovered'] ?? false], $operation['state'] === 'succeeded' ? 'success' : 'failure');
                    } catch (Throwable $auditError) {
                        report($auditError);
                    }
                } finally {
                    // A journal/disk failure must not leave a safely recovered application down.
                    if ($maintenance && $safe) {
                        try {
                            $up = Artisan::call('up') === 0;
                        } catch (Throwable $maintenanceError) {
                            report($maintenanceError);
                            $up = false;
                        }
                        if (! $up) {
                            $operation['maintenance_retained'] = true;
                            $operation['message'] .= ' Không thể kết thúc bảo trì; quản trị máy chủ cần kiểm tra.';
                            $this->write($operation);
                        }
                    }
                }
            }

            return $operation;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function invalidateSessions(): void
    {
        DB::table('sessions')->delete();
        DB::table('password_reset_tokens')->delete();
        DB::table('users')->update(['session_version' => DB::raw('session_version + 1'), 'remember_token' => null]);
    }

    private function verifyRelations(): void
    {
        $connection = DB::connection();
        if ($connection->getDriverName() === 'sqlite') {
            if ($connection->select('PRAGMA foreign_key_check')) {
                throw new RuntimeException('Bản sao lưu có tham chiếu không hợp lệ.');
            }

            return;
        }
        foreach ($connection->getSchemaBuilder()->getTableListing(schema: $connection->getDatabaseName(), schemaQualified: false) as $table) {
            foreach ($connection->getSchemaBuilder()->getForeignKeys($table) as $foreign) {
                $query = DB::table($table.' as child')->leftJoin($foreign['foreign_table'].' as parent', function ($join) use ($foreign): void {
                    foreach ($foreign['columns'] as $i => $column) {
                        $join->on('child.'.$column, '=', 'parent.'.$foreign['foreign_columns'][$i]);
                    }
                })->whereNull('parent.'.$foreign['foreign_columns'][0]);
                foreach ($foreign['columns'] as $column) {
                    $query->whereNotNull('child.'.$column);
                }
                if ($query->exists()) {
                    throw new RuntimeException('Bản sao lưu có tham chiếu không hợp lệ.');
                }
            }
        }
    }
}
