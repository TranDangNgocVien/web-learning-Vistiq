<div class="auth-field">
    <div class="auth-label-line">
        <label for="{{ $fieldId }}">{{ $fieldLabel }}</label>
    </div>
    <div class="auth-input-wrap auth-password-wrap">
        <svg aria-hidden="true" viewBox="0 0 20 20" focusable="false"><path d="M6 8V6a4 4 0 1 1 8 0v2m-9.5 0h11A1.5 1.5 0 0 1 17 9.5v6A1.5 1.5 0 0 1 15.5 17h-11A1.5 1.5 0 0 1 3 15.5v-6A1.5 1.5 0 0 1 4.5 8Zm5.5 3v2"/></svg>
        <input id="{{ $fieldId }}" name="{{ $fieldId }}" type="password" autocomplete="{{ $fieldAutocomplete }}" placeholder="{{ $fieldPlaceholder }}" required aria-invalid="{{ $errors->has($fieldId) ? 'true' : 'false' }}" aria-describedby="auth-password-help{{ $errors->has($fieldId) ? ' '.$fieldId.'-error' : '' }}" @if($minlength ?? false) data-minlength="{{ $minlength }}" @endif>
        <button class="password-toggle" type="button" data-password-toggle aria-label="Hiện {{ mb_strtolower($fieldLabel) }}" aria-controls="{{ $fieldId }}" aria-pressed="false">
            <svg class="password-eye-open" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.6"/></svg>
            <svg class="password-eye-closed" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m3 3 18 18M10.6 6.1c.5-.1.9-.1 1.4-.1 6.1 0 9.5 6 9.5 6a16.2 16.2 0 0 1-3 3.6M6.2 6.3C3.8 8 2.5 12 2.5 12s3.4 6 9.5 6c1.1 0 2.1-.2 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
        </button>
    </div>
    @if ($showForgotLink ?? false)
        <a class="auth-forgot-link" href="{{ route('password.request') }}">Quên mật khẩu?</a>
    @endif
    @if ($hasPasswordRules ?? false)
        <div class="password-rule-tracker is-empty" id="{{ $fieldId }}-rules" data-password-rules data-min="{{ $minlength ?? 8 }}" aria-live="polite">
            <div class="password-rule-bar" aria-hidden="true">
                <div class="password-rule-progress" style="width: 0%"></div>
            </div>
            <div class="password-rule-status">
                <span class="password-rule-count" data-rule-count>0/{{ $minlength ?? 8 }} ký tự</span>
                <span class="password-rule-message" data-rule-message>Tối thiểu {{ $minlength ?? 8 }} ký tự, gồm chữ và số</span>
            </div>
        </div>
    @endif
    @error($fieldId)
        <span class="auth-field-error" id="{{ $fieldId }}-error" role="alert">{{ $message }}</span>
    @enderror
</div>
