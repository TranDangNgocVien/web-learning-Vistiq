<?php

namespace VistiqLearner\Http\Requests\Authentication;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordRule::min(8)->letters()->numbers(), 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nhập họ và tên của bạn.',
            'name.max' => 'Họ và tên không được dài quá :max ký tự.',
            'email.required' => 'Nhập địa chỉ email của bạn.',
            'email.email' => 'Email chưa đúng định dạng.',
            'email.max' => 'Email không được dài quá :max ký tự.',
            'email.unique' => 'Email này đã có tài khoản.',
            'password.required' => 'Tạo mật khẩu cho tài khoản.',
            'password.min' => 'Mật khẩu cần ít nhất :min ký tự.',
            'password.letters' => 'Mật khẩu cần có ít nhất một chữ cái.',
            'password.numbers' => 'Mật khẩu cần có ít nhất một chữ số.',
            'password.confirmed' => 'Hai mật khẩu chưa khớp.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(trim((string) $this->input('email'))),
            ]);
        }
    }
}
