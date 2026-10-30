<?php

namespace VistiqCore\Services\System;

class SettingsSchema
{
    public const RULES = [
        'general' => ['site_name' => 'required|string|max:100', 'timezone' => 'required|timezone', 'learner_url' => 'required|url', 'admin_url' => 'required|url'],
        'exam' => ['duration_minutes' => 'required|integer|min:1|max:480', 'allow_mobile' => 'required|boolean', 'autosave_seconds' => 'required|integer|min:1|max:60'],
        'security' => ['admin_otp' => 'required|boolean', 'log_ip' => 'required|boolean', 'warning_threshold' => 'required|integer|min:0|max:100', 'auto_submit_threshold' => 'required|integer|min:0|max:100'],
        'email' => ['mailer' => 'required|in:log,smtp', 'host' => 'nullable|string|max:255', 'port' => 'required|integer|min:1|max:65535', 'username' => 'nullable|string|max:255', 'password' => 'nullable|string|max:2000', 'from_address' => 'required|email', 'from_name' => 'required|string|max:100'],
        'ai' => ['enabled' => 'required|boolean', 'endpoint' => 'required|url:http,https', 'model' => 'nullable|string|max:100', 'api_key' => 'nullable|string|max:2000', 'daily_limit' => 'required|integer|min:1|max:10000', 'audio_capable' => 'required|boolean', 'transcription_model' => 'nullable|string|max:100', 'writing_evaluation' => 'required|boolean', 'speaking_evaluation' => 'required|boolean', 'translation' => 'required|boolean', 'explanation_generation' => 'required|boolean'],
        'media' => ['max_megabytes' => 'required|integer|min:1|max:100', 'cdn_url' => 'nullable|url:https'],
        'storage' => ['disk' => 'required|in:public,s3', 'key' => 'nullable|string|max:2000', 'secret' => 'nullable|string|max:2000', 'region' => 'required|string|max:100', 'bucket' => 'nullable|string|max:100', 'endpoint' => 'nullable|url', 'url' => 'nullable|url'],
        'scoring' => ['estimated' => 'required|boolean', 'scales' => 'sometimes|array|max:20', 'scales.*.name' => 'required|string|max:140', 'scales.*.id' => 'sometimes|integer|exists:score_scales,id', 'scales.*.configuration' => 'required|array', 'scales.*.configuration.tables' => 'required|array', 'scales.*.configuration.tables.*' => 'required|array', 'scales.*.configuration.tables.*.*' => 'required|numeric|min:0|max:990'],
        'feature_flags' => ['leaderboard' => 'required|boolean', 'ai' => 'required|boolean'],
    ];

    public const LABELS = [
        'general' => 'Thông tin chung', 'exam' => 'Làm đề', 'security' => 'Bảo mật', 'email' => 'Email', 'ai' => 'Trí tuệ nhân tạo', 'media' => 'Tệp đa phương tiện', 'storage' => 'Lưu trữ', 'scoring' => 'Thang điểm', 'feature_flags' => 'Tính năng',
        'site_name' => 'Tên website', 'timezone' => 'Múi giờ', 'learner_url' => 'URL học viên', 'admin_url' => 'URL quản trị',
        'duration_minutes' => 'Thời lượng mặc định (phút)', 'allow_mobile' => 'Cho phép điện thoại', 'autosave_seconds' => 'Chu kỳ tự lưu (giây)',
        'admin_otp' => 'OTP khi đăng nhập Admin', 'log_ip' => 'Ghi địa chỉ IP', 'warning_threshold' => 'Ngưỡng cảnh báo', 'auto_submit_threshold' => 'Ngưỡng tự nộp (0 để tắt)',
        'mailer' => 'Phương thức gửi', 'host' => 'Máy chủ SMTP', 'port' => 'Cổng SMTP', 'username' => 'Tên đăng nhập SMTP', 'password' => 'Mật khẩu SMTP', 'from_address' => 'Email người gửi', 'from_name' => 'Tên người gửi',
        'enabled' => 'Bật chức năng', 'endpoint' => 'URL dịch vụ', 'model' => 'Mô hình AI', 'api_key' => 'API key', 'daily_limit' => 'Giới hạn mỗi ngày', 'audio_capable' => 'Hỗ trợ âm thanh', 'transcription_model' => 'Mô hình chuyển giọng nói', 'writing_evaluation' => 'Gợi ý chấm Writing', 'speaking_evaluation' => 'Gợi ý chấm Speaking', 'translation' => 'Dịch nội dung', 'explanation_generation' => 'Tạo giải thích',
        'max_megabytes' => 'Dung lượng tối đa (MB)', 'cdn_url' => 'URL CDN', 'disk' => 'Kho lưu trữ', 'key' => 'Access key', 'secret' => 'Secret key', 'region' => 'Khu vực', 'bucket' => 'Bucket', 'url' => 'URL công khai',
        'estimated' => 'Điểm ước tính', 'leaderboard' => 'Bảng xếp hạng',
    ];
}
