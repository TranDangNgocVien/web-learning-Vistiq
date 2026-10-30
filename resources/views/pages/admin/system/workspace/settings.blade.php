@include('pages.admin.system.workspace.nav')
@php($labels=\VistiqCore\Services\System\SettingsSchema::LABELS)
<form method="get" class="filter-panel"><label>Nhóm cấu hình<select name="group">@foreach($groups as $group=>$value)<option value="{{ $group }}" @selected($selectedGroup===$group)>{{ $labels[$group] }}</option>@endforeach</select></label><button class="button button-quiet" type="submit">Mở nhóm</button></form>
<section class="panel"><h2>{{ $labels[$selectedGroup] }}</h2><p>Các trường có * là bắt buộc. Lưu từng nhóm để áp dụng thay đổi.</p>
<form class="form-grid" method="post" action="{{ route('admin.settings.update',$selectedGroup) }}" data-pending-label="Đang lưu…">@csrf @method('PUT')<input type="hidden" name="typed_form" value="1">
@php($scalarValues=collect($groups[$selectedGroup])->reject(fn($value)=>is_array($value)))
@foreach($scalarValues as $field=>$fieldValue)@include('pages.admin.system.workspace.field')@endforeach
<input type="hidden" name="field_count" value="{{ $scalarValues->count() }}"><div class="form-wide"><button class="button button-primary" type="submit">Lưu {{ mb_strtolower($labels[$selectedGroup]) }}</button></div></form></section>
@if($selectedGroup==='scoring')
@foreach($groups['scoring']['scales'] as $scale)
<section class="panel"><h2>{{ $scale['name'] }}</h2><form method="post" action="{{ route('admin.settings.update','scoring') }}" data-pending-label="Đang lưu…">@csrf @method('PUT')<input type="hidden" name="typed_form" value="1"><input type="hidden" name="value[estimated]" value="{{ (int)$groups['scoring']['estimated'] }}"><input type="hidden" name="value[scales][0][id]" value="{{ $scale['id'] }}">
<label class="form-field">Tên thang điểm *<input name="value[scales][0][name]" value="{{ old('value.scales.0.id')==$scale['id']?old('value.scales.0.name',$scale['name']):$scale['name'] }}" required maxlength="140"></label>
@foreach($scale['configuration']['tables']??[] as $table=>$scores)<details><summary>Bảng {{ $table }} ({{ count($scores) }} mức)</summary><div class="form-grid">@foreach($scores as $raw=>$scaled)<label class="form-field">{{ $raw }} câu đúng → điểm *<input type="number" step="any" min="0" max="990" name="value[scales][0][configuration][tables][{{ $table }}][{{ $raw }}]" value="{{ old('value.scales.0.id')==$scale['id']?old('value.scales.0.configuration.tables.'.$table.'.'.$raw,$scaled):$scaled }}" required></label>@endforeach</div></details>@endforeach
<input type="hidden" name="field_count" value="{{ 3+count(\Illuminate\Support\Arr::dot($scale['configuration']['tables']??[])) }}"><button class="button button-primary" type="submit">Lưu thang điểm</button></form></section>@endforeach
@endif
