<?php

namespace VistiqAdmin\Http\Controllers\Admin\System;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use VistiqAdmin\Http\Controllers\Admin\AdminPageController;
use VistiqAdmin\Http\Requests\Admin\Shared\AdminActionRequest;
use VistiqAdmin\Http\Requests\Admin\System\AuditFilterRequest;
use VistiqAdmin\Http\Requests\Admin\System\SettingsUpdateRequest;
use VistiqCore\Services\Shared\AiService;
use VistiqCore\Services\System\AuditService;
use VistiqCore\Services\System\SettingsService;

class SettingsController extends AdminPageController
{
    private const AUDIT_ENTITY_LABELS = [
        'question' => 'Câu hỏi',
        'exam' => 'Đề',
        'stimulus' => 'Bài Reading',
        'knowledge' => 'Mục kiến thức',
        'settings' => 'Cài đặt',
        'user' => 'Người dùng',
        'role' => 'Vai trò',
        'database' => 'Cơ sở dữ liệu',
        'media' => 'Tài nguyên',
        'group' => 'Nhóm câu hỏi',
        'question_group' => 'Nhóm câu hỏi',
        'attempt' => 'Lượt thi',
        'answer' => 'Câu trả lời',
        'analytics' => 'Báo cáo',
        'leaderboard' => 'Bảng xếp hạng',
    ];

    private const AUDIT_ACTION_VERBS = [
        'save' => 'Lưu',
        'create' => 'Tạo',
        'update' => 'Cập nhật',
        'delete' => 'Xóa',
        'restore' => 'Khôi phục',
        'publish' => 'Xuất bản',
        'archive' => 'Lưu trữ',
        'approve' => 'Duyệt',
        'reject' => 'Trả chỉnh sửa',
        'moderate' => 'Điều chỉnh',
        'grade' => 'Chấm',
        'upload' => 'Tải lên',
        'download' => 'Tải xuống',
        'remove' => 'Gỡ',
        'assign' => 'Gán',
        'import' => 'Nhập',
        'export' => 'Xuất',
        'reset' => 'Đặt lại',
        'lock' => 'Khóa',
        'unlock' => 'Mở khóa',
    ];

    private const AUDIT_ACTION_PHRASES = [
        'settings.update' => 'Cập nhật cài đặt',
        'analytics.export' => 'Xuất báo cáo',
        'leaderboard.export' => 'Xuất bảng xếp hạng',
        'leaderboard.reset' => 'Đặt lại bảng xếp hạng',
        'database.backup.create' => 'Tạo bản sao lưu',
        'database.backup.download' => 'Tải bản sao lưu',
        'database.restore.preflight' => 'Kiểm tra khôi phục cơ sở dữ liệu',
        'database.restore.request' => 'Yêu cầu khôi phục cơ sở dữ liệu',
        'database.restore.result' => 'Kết quả khôi phục cơ sở dữ liệu',
        'question-group.audio.assign' => 'Gán âm thanh cho nhóm câu hỏi',
        'question-group.audio.remove' => 'Gỡ âm thanh khỏi nhóm câu hỏi',
    ];

    private const AUDIT_FIELD_LABELS = [
        'status' => 'Trạng thái',
        'draft_status' => 'Trạng thái bản nháp',
        'revision' => 'Revision',
        'revision_counter' => 'Revision',
        'title' => 'Tiêu đề',
        'roles' => 'Vai trò',
        'permissions' => 'Quyền',
        'note' => 'Ghi chú',
        'leaderboard_status' => 'Trạng thái bảng xếp hạng',
        'soft_deleted' => 'Đã xóa mềm',
        'deleted' => 'Đã xóa',
        'actor_id' => 'Người thực hiện',
        'user_id' => 'Người dùng',
        'entity_id' => 'ID đối tượng',
        'artifact_id' => 'ID bản sao lưu',
        'operation_id' => 'ID thao tác',
        'format' => 'Định dạng',
        'rows' => 'Số dòng',
        'imported' => 'Số mục đã nhập',
        'duplicate_skipped' => 'Số mục trùng đã bỏ qua',
        'checksum' => 'Mã kiểm tra',
        'created_at' => 'Thời điểm tạo',
        'updated_at' => 'Thời điểm cập nhật',
        'result' => 'Kết quả',
    ];

    private const AUDIT_STATUS_LABELS = [
        'active' => 'Hoạt động',
        'locked' => 'Đã khóa',
        'disabled' => 'Đã tắt',
        'draft' => 'Bản nháp',
        'review' => 'Chờ duyệt',
        'approved' => 'Đã duyệt',
        'published' => 'Đã xuất bản',
        'archived' => 'Đã lưu trữ',
        'rejected' => 'Trả chỉnh sửa',
        'pending' => 'Đang xử lý',
        'success' => 'Thành công',
        'failure' => 'Thất bại',
        'succeeded' => 'Thành công',
        'failed' => 'Thất bại',
    ];

    public function index(Request $request, SettingsService $settings): mixed
    {
        $selectedGroup = $request->query('group', 'general');
        abort_unless(array_key_exists($selectedGroup, SettingsService::DEFAULTS), 404);
        $configured = [];
        $groups = [];
        foreach (array_keys(SettingsService::DEFAULTS) as $group) {
            $value = $settings->get($group);
            foreach (['password', 'api_key', 'key', 'secret'] as $field) {
                if (array_key_exists($field, $value)) {
                    $configured[$group][$field] = filled($value[$field]);
                    $value[$field] = '';
                }
            } $groups[$group] = $value;
        }
        $groups['scoring']['scales'] = DB::table('score_scales')->get()->map(fn ($scale) => ['id' => $scale->id, 'name' => $scale->name, 'version' => $scale->version, 'configuration' => json_decode($scale->configuration ?? '{}', true)])->all();
        foreach ($groups['scoring']['scales'] as &$scale) {
            unset($scale['version']);
        } unset($scale);

        return $this->page('Cài đặt hệ thống', 'pages.admin.system.workspace.settings', compact('groups', 'selectedGroup', 'configured'));
    }

    public function update(SettingsUpdateRequest $request, string $group, SettingsService $settings): mixed
    {
        $value = $request->validated('value');
        $effective = array_replace($settings->get($group), $value);
        foreach (['password', 'api_key', 'key', 'secret'] as $secret) {
            if (array_key_exists($secret, $value) && blank($value[$secret])) {
                $effective[$secret] = $settings->get($group)[$secret] ?? '';
            }
        }
        if ($group === 'security' && $value['auto_submit_threshold'] > 0 && $value['warning_threshold'] > $value['auto_submit_threshold']) {
            throw ValidationException::withMessages(['value.auto_submit_threshold' => 'Ngưỡng tự nộp phải lớn hơn hoặc bằng ngưỡng cảnh báo.']);
        }
        if ($group === 'email' && $value['mailer'] === 'smtp' && blank($value['host'] ?? null)) {
            throw ValidationException::withMessages(['value.host' => 'Nhập máy chủ SMTP trước khi chọn gửi bằng SMTP.']);
        }
        if ($group === 'email' && $settings->get('security')['admin_otp'] && $value['mailer'] !== 'smtp') {
            throw ValidationException::withMessages(['value.mailer' => 'Tắt OTP Admin trước khi tắt SMTP.']);
        }
        if ($group === 'ai' && $value['enabled']) {
            foreach (['model', 'api_key'] as $field) {
                if (blank($effective[$field])) {
                    throw ValidationException::withMessages(['value.'.$field => 'Cấu hình mô hình và API key trước khi bật AI.']);
                }
            }
            if ($value['speaking_evaluation'] && (! $value['audio_capable'] || blank($value['transcription_model']))) {
                throw ValidationException::withMessages(['value.transcription_model' => 'Chấm Speaking cần hỗ trợ âm thanh và mô hình chuyển giọng nói.']);
            }
        }
        if ($group === 'storage' && $value['disk'] === 's3') {
            foreach (['bucket', 'key', 'secret'] as $field) {
                if (blank($effective[$field])) {
                    throw ValidationException::withMessages(['value.'.$field => 'Cấu hình bucket, access key và secret key trước khi dùng S3.']);
                }
            }
        }
        if ($group === 'security' && $value['admin_otp']) {
            $email = $settings->get('email');
            if ($email['mailer'] !== 'smtp' || blank($email['host'])) {
                throw ValidationException::withMessages(['value.admin_otp' => 'Cấu hình email SMTP trước khi bật OTP.']);
            }
        }
        DB::transaction(function () use ($group, $value, $settings): void {
            foreach ($value['scales'] ?? [] as $scale) {
                $attributes = ['name' => $scale['name'], 'configuration' => json_encode($scale['configuration']), 'updated_at' => now()];
                if (isset($scale['id'])) {
                    $current = DB::table('score_scales')->where('id', $scale['id'])->lockForUpdate()->first();
                    DB::table('score_scales')->where('id', $scale['id'])->update([...$attributes, 'version' => $current->version + 1]);
                } else {
                    DB::table('score_scales')->insert([...$attributes, 'version' => 1, 'created_at' => now()]);
                }
            }
            unset($value['scales'],$value['plans']);
            $settings->put($group, $value);
        });

        return back()->with('status', 'Đã lưu cấu hình '.$group.'.');
    }

    public function audit(AuditFilterRequest $request, AuditService $audit): mixed
    {
        $filters = $request->validated();
        $query = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')->select('audit_logs.*', 'users.name as actor_name')->where('audit_logs.entity_type', '<>', 'activity');
        foreach (['actor' => 'actor_id', 'action' => 'action', 'entity' => 'entity_type', 'entity_id' => 'entity_id', 'result' => 'result'] as $filter => $column) {
            if (filled($filters[$filter] ?? null)) {
                $query->where('audit_logs.'.$column, $filters[$filter]);
            }
        }
        if (filled($filters['from'] ?? null)) {
            $query->where('audit_logs.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (filled($filters['to'] ?? null)) {
            $query->where('audit_logs.created_at', '<', Carbon::parse($filters['to'])->addDay()->toDateString());
        }
        $logs = $query->orderByDesc('audit_logs.id')->paginate(30)->withQueryString();
        foreach ($logs as $log) {
            foreach (['before', 'after'] as $field) {
                $log->$field = $audit->redact(json_decode($log->$field ?? '{}', true) ?: []);
            }
            $log->action_label = $this->auditActionLabel($log);
            $log->entity_label = $this->auditEntityLabel((string) $log->entity_type, $log->entity_id);
            $log->diff = $this->auditDiff($log->before, $log->after);
        }
        return $this->page('Audit log', 'pages.admin.system.workspace.audit', compact('logs', 'filters'));
    }

    private function auditActionLabel(object $log): string
    {
        $action = (string) $log->action;
        $entityId = $log->entity_id ?? ($log->after['artifact_id'] ?? null);
        if (isset(self::AUDIT_ACTION_PHRASES[$action])) {
            return $this->numberedAuditLabel(self::AUDIT_ACTION_PHRASES[$action], $entityId);
        }

        $segments = explode('.', $action);
        $verb = (string) end($segments);
        $verbLabel = self::AUDIT_ACTION_VERBS[$verb] ?? 'Thay đổi';
        $target = $this->auditEntityLabel((string) $log->entity_type, $log->entity_id);
        $target = mb_strtolower(mb_substr($target, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($target, 1, null, 'UTF-8');

        return $verbLabel.' '.$target;
    }

    private function auditEntityLabel(string $entity, mixed $id = null): string
    {
        $label = self::AUDIT_ENTITY_LABELS[$entity] ?? $this->humanizeAuditLabel($entity);

        return $this->numberedAuditLabel($label, $id);
    }

    private function numberedAuditLabel(string $label, mixed $id): string
    {
        if (! is_numeric($id)) {
            return $label;
        }

        return $label.' #'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    private function auditDiff(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $hasBefore = array_key_exists($field, $before);
            $hasAfter = array_key_exists($field, $after);
            if ($hasBefore && $hasAfter && $before[$field] === $after[$field]) {
                continue;
            }

            $field = (string) $field;
            $changes[] = [
                'label' => self::AUDIT_FIELD_LABELS[$field] ?? $this->humanizeAuditLabel($field),
                'before' => $hasBefore ? $this->auditValue($before[$field], $field) : '—',
                'after' => $hasAfter ? $this->auditValue($after[$field], $field) : '—',
            ];
        }

        return $changes;
    }

    private function auditValue(mixed $value, string $field): string
    {
        if ($value === null) {
            return 'Trống';
        }
        if (is_bool($value)) {
            return $value ? 'Có' : 'Không';
        }
        if (is_array($value)) {
            if ($value === []) {
                return 'Không có mục';
            }

            return array_is_list($value) ? count($value).' mục' : count($value).' trường chi tiết';
        }
        if (! is_scalar($value)) {
            return 'Đã cập nhật';
        }

        $value = (string) $value;
        if (in_array($field, ['status', 'draft_status', 'leaderboard_status', 'result', 'state'], true)) {
            return self::AUDIT_STATUS_LABELS[strtolower($value)] ?? $value;
        }

        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['{', '['], true)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return 'Đã cập nhật dữ liệu chi tiết';
            }
        }

        return mb_strlen($value) > 180 ? mb_substr($value, 0, 177).'…' : $value;
    }

    private function humanizeAuditLabel(string $value): string
    {
        $words = preg_replace('/[._-]+/u', ' ', trim($value)) ?: $value;

        return mb_convert_case($words, MB_CASE_TITLE, 'UTF-8');
    }

    public function jobs(): mixed
    {
        $jobs = DB::table('jobs')->select('id', 'queue', 'attempts', 'reserved_at', 'available_at')->latest('id')->paginate(20, ['*'], 'jobs_page')->withQueryString();
        $failed = DB::table('failed_jobs')->select('id', 'queue', 'failed_at')->latest('id')->paginate(20, ['*'], 'failed_page')->withQueryString();

        return $this->page('Trạng thái tác vụ', 'pages.admin.system.workspace.jobs', compact('jobs', 'failed'));
    }

    public function ai(AdminActionRequest $request, AiService $ai): mixed
    {
        $data = $request->validated();
        abort_unless(! empty($data['purpose']) && ! empty($data['input']), 422);

        return response()->json(['id' => $ai->enqueue($data['purpose'], $data['input'], $request->user()->id)], 202);
    }

    public function aiStatus(int $task): mixed
    {
        $task = DB::table('ai_tasks')->find($task);
        abort_unless($task, 404);
        abort_unless((int) $task->actor_id === auth()->id() || auth()->user()->hasPermission('results.grade'), 404);

        return response()->json(['id' => $task->id, 'status' => $task->status, 'result' => json_decode($task->result ?? 'null', true), 'error' => $task->error]);
    }
}
