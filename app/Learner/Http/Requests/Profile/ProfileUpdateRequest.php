<?php

namespace VistiqLearner\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active' && ! $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255', 'country_code' => 'nullable|string|size:2|regex:/^[A-Z]{2}$/D',
            'time_zone' => 'nullable|string|max:64|timezone', 'bio' => 'nullable|string|max:1000',
            'avatar' => 'nullable|file|image|mimes:jpg,jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:2048|dimensions:min_width=1,min_height=1,max_width=4096,max_height=4096',
            'remove_avatar' => 'sometimes|boolean',
            'user_id' => 'prohibited', 'email' => 'prohibited', 'password' => 'prohibited',
            'role' => 'prohibited', 'roles' => 'prohibited', 'permission' => 'prohibited', 'permissions' => 'prohibited',
            'status' => 'prohibited', 'plan_id' => 'prohibited', 'subscription' => 'prohibited',
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'country_code', 'time_zone', 'bio'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => $field === 'country_code' ? strtoupper(trim($this->input($field))) : trim($this->input($field))]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->hasFile('avatar') && $this->boolean('remove_avatar')) {
                $validator->errors()->add('avatar', 'Chọn ảnh mới hoặc bỏ ảnh hiện tại, không thực hiện cùng lúc.');
            }
        });
    }

    public function messages(): array
    {
        return ['avatar.max' => 'Ảnh đại diện tối đa 2 MB.', 'avatar.image' => 'Tệp phải là ảnh JPEG, PNG hoặc WebP thật.', 'avatar.dimensions' => 'Ảnh phải có kích thước từ 1 đến 4.096 pixel mỗi chiều.', 'country_code.regex' => 'Nhập mã quốc gia hai chữ cái, ví dụ VN.', 'bio.max' => 'Giới thiệu tối đa 1.000 ký tự.'];
    }
}
