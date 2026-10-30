@php
$rule = \VistiqCore\Services\System\SettingsSchema::RULES[$selectedGroup][$field] ?? '';
$label = \VistiqCore\Services\System\SettingsSchema::LABELS[$field] ?? $field;
$secret = in_array($field,['password','api_key','key','secret']);
$inputValue = $secret ? '' : old('value.'.$field, $fieldValue);
$inputId = 'setting-'.$field;
$enum = preg_match('/(?:^|\|)in:([^|]+)/',$rule,$matches) ? explode(',',$matches[1]) : [];
$type = $secret ? 'password' : (str_contains($rule,'integer') ? 'number' : (str_contains($rule,'email') ? 'email' : (str_contains($rule,'url') ? 'url' : 'text')));
$max = preg_match('/(?:^|\|)max:(\d+)/',$rule,$m) ? $m[1] : null;
$min = preg_match('/(?:^|\|)min:(\d+)/',$rule,$m) ? $m[1] : null;
@endphp
<label class="form-field" for="{{ $inputId }}">{{ $label }}{{ str_starts_with($rule,'required') ? ' *' : '' }}
@if(str_contains($rule,'boolean'))<input type="hidden" name="value[{{ $field }}]" value="0"><span><input id="{{ $inputId }}" type="checkbox" name="value[{{ $field }}]" value="1" @checked($inputValue) aria-describedby="{{ $inputId }}-error"> Bật</span>
@elseif($enum)<select id="{{ $inputId }}" name="value[{{ $field }}]" aria-describedby="{{ $inputId }}-error">@foreach($enum as $option)<option value="{{ $option }}" @selected($inputValue===$option)>{{ $option }}</option>@endforeach</select>
@else<input id="{{ $inputId }}" name="value[{{ $field }}]" value="{{ $inputValue }}" type="{{ $type }}" @if($type==='password') autocomplete="new-password" @endif @if(str_starts_with($rule,'required')) required @endif @if($max) {{ $type==='number'?'max':'maxlength' }}="{{ $max }}" @endif @if($min && $type==='number') min="{{ $min }}" @endif aria-describedby="{{ $inputId }}-help {{ $inputId }}-error">
<small id="{{ $inputId }}-help">@if($secret){{ ($configured[$selectedGroup][$field]??false)?'Đã cấu hình':'Chưa cấu hình' }}. Để trống để giữ giá trị hiện tại.@elseif($type==='number'){{ $min??0 }}–{{ $max??'không giới hạn' }}.@elseif($type==='url')URL đầy đủ, gồm giao thức.@elseif($field==='timezone')Ví dụ Asia/Ho_Chi_Minh.@elseif($max)Tối đa {{ $max }} ký tự.@endif</small>@endif
<span class="form-field-error" id="{{ $inputId }}-error">@error('value.'.$field){{ $message }}@enderror</span></label>
