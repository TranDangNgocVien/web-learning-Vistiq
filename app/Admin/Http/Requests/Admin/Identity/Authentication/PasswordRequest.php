<?php

namespace VistiqAdmin\Http\Requests\Admin\Identity\Authentication;

use Illuminate\Foundation\Http\FormRequest;

class PasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ['email' => 'required|email|max:254'];
        if ($this->routeIs('admin.login.store', 'api.admin.login.store')) {
            $rules['password'] = 'required|string';
            $rules['remember'] = 'sometimes|boolean';
        }
        if ($this->routeIs('admin.password.update', 'api.admin.password.update')) {
            $rules['token'] = 'required|string';
            $rules['password'] = 'required|string|min:10|max:128|confirmed';
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Nhập địa chỉ email của bạn.',
            'email.email' => 'Email chưa đúng định dạng.',
            'password.required' => 'Nhập mật khẩu.',
            'password.min' => 'Mật khẩu cần ít nhất :min ký tự.',
            'password.max' => 'Mật khẩu không được vượt quá :max ký tự.',
            'password.confirmed' => 'Hai mật khẩu chưa khớp.',
            'token.required' => 'Liên kết không hợp lệ. Hãy yêu cầu liên kết khôi phục mới.',
        ];
    }
}
