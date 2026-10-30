<?php

namespace VistiqLearner\Http\Requests\Authentication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::min(8)->letters()->numbers(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Nhập địa chỉ email của bạn.',
            'email.email' => 'Email chưa đúng định dạng.',
            'password.required' => 'Tạo mật khẩu mới.',
            'password.min' => 'Mật khẩu cần ít nhất :min ký tự.',
            'password.letters' => 'Mật khẩu cần có ít nhất một chữ cái.',
            'password.numbers' => 'Mật khẩu cần có ít nhất một chữ số.',
            'password.confirmed' => 'Hai mật khẩu chưa khớp.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
