<?php

namespace VistiqCore\Services\System;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditService
{
    public function record(string $action, string $type, ?int $id, array $before = [], array $after = [], string $result = 'success'): void
    {
        $actor = Auth::id();
        DB::table('audit_logs')->insert(['actor_id' => $actor && DB::table('users')->where('id', $actor)->exists() ? $actor : null, 'action' => $action, 'entity_type' => $type, 'entity_id' => $id, 'result' => in_array($result, ['success', 'failure', 'pending'], true) ? $result : 'failure', 'before' => json_encode($this->redact($before), JSON_UNESCAPED_UNICODE), 'after' => json_encode($this->redact($after), JSON_UNESCAPED_UNICODE), 'created_at' => now()]);
    }

    public function redact(array $value): array
    {
        foreach ($value as $key => $item) {
            if (preg_match('/password|secret|api.?key|token|otp|code_hash|^key$|email|phone|ip_address|personal_note/i', (string) $key)) {
                $value[$key] = '[redacted]';
            } elseif (is_array($item)) {
                $value[$key] = $this->redact($item);
            }
        }

        return $value;
    }
}
