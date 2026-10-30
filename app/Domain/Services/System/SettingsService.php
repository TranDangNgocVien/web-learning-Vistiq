<?php

namespace VistiqCore\Services\System;

use VistiqCore\Models\SystemSetting;

class SettingsService
{
    public const DEFAULTS = [
        'general' => ['site_name' => 'Vistiq', 'timezone' => 'Asia/Bangkok', 'learner_url' => 'http://vistiq.test', 'admin_url' => 'http://vistiq-admin.test'],
        'exam' => ['duration_minutes' => 30, 'allow_mobile' => true, 'autosave_seconds' => 3],
        'security' => ['admin_otp' => false, 'log_ip' => false, 'warning_threshold' => 2, 'auto_submit_threshold' => 3],
        'email' => ['mailer' => 'log', 'host' => '', 'port' => 587, 'username' => '', 'password' => '', 'from_address' => 'admin@vistiq.test', 'from_name' => 'Vistiq'],
        'ai' => ['enabled' => false, 'endpoint' => 'https://api.openai.com/v1', 'model' => '', 'api_key' => '', 'daily_limit' => 20, 'audio_capable' => false, 'transcription_model' => '', 'writing_evaluation' => false, 'speaking_evaluation' => false, 'translation' => false, 'explanation_generation' => false],
        'media' => ['max_megabytes' => 50, 'cdn_url' => ''],
        'storage' => ['disk' => 'public', 'key' => '', 'secret' => '', 'region' => 'us-east-1', 'bucket' => '', 'endpoint' => '', 'url' => ''],
        'scoring' => ['estimated' => true],
        'feature_flags' => ['leaderboard' => true, 'ai' => false],
    ];

    public function get(string $group): array
    {
        return array_replace(self::DEFAULTS[$group] ?? [], SystemSetting::where('group', $group)->first()?->value ?? []);
    }

    public function put(string $group, array $value): void
    {
        $before = $this->get($group);
        foreach (['password', 'api_key', 'key', 'secret'] as $field) {
            if (array_key_exists($field, $value) && blank($value[$field])) {
                unset($value[$field]);
            }
        }
        $value = array_replace($before, $value);
        SystemSetting::updateOrCreate(['group' => $group], ['value' => $value]);
        app(AuditService::class)->record('settings.update', 'settings', null, [$group => $before], [$group => $value]);
    }

    public function configureMail(): void
    {
        $mail = $this->get('email');
        if ($mail['mailer'] === 'smtp') {
            config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => $mail['host'], 'mail.mailers.smtp.port' => $mail['port'], 'mail.mailers.smtp.username' => $mail['username'], 'mail.mailers.smtp.password' => $mail['password'], 'mail.from.address' => $mail['from_address'], 'mail.from.name' => $mail['from_name']]);
            app('mail.manager')->purge('smtp');
        }
    }
}
