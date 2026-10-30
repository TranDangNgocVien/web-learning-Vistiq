@include('pages.admin.identity.users-and-permissions.workspace.appearance')
@php
    $isSuperAdmin = $selectedRole?->code === 'super-admin';
    $isSystemRole = in_array($selectedRole?->code, ['admin', 'super-admin'], true);
    $creating = old('_identity_form') === 'create';
    $editing = old('_identity_form') === 'edit';
    $chosenPermissions = $editing ? array_map('intval', (array) old('permissions', [])) : ($selectedRole?->permissions->pluck('id')->all() ?? []);
    $permissionCount = collect($permissionGroups)->sum(fn (array $group): int => count($group['permissions']));
@endphp
<div class="identity-workspace" data-identity-workspace>
    <header class="page-heading"><div><h1>Phân quyền theo vai trò</h1><p>Gán quyền từ danh mục {{ $permissionCount }} quyền hệ thống.</p></div>@can('users.manage')<a class="button button-quiet" href="{{ route('admin.users.index') }}">Quản lý người dùng</a>@endcan</header>
    <details class="panel identity-create" @if($creating)open @endif>
        <summary>Thêm vai trò mới</summary>
        <form method="post" action="{{ route('admin.roles.store') }}" class="admin-form-grid" data-pending-label="Đang tạo vai trò…">
            @csrf<input type="hidden" name="_identity_form" value="create">
            <label for="create-role-code">Mã vai trò *
                <input id="create-role-code" name="code" value="{{ $creating ? old('code') : '' }}" pattern="[a-z0-9\-]+" maxlength="40" placeholder="vi-du: teacher-lead" required aria-describedby="create-code-help @if($creating && $errors->has('code'))create-code-error @endif" @if($creating && $errors->has('code'))aria-invalid="true"@endif>
                <small id="create-code-help">Tối đa 40 ký tự: chữ thường, số, gạch ngang. Không đổi sau khi tạo.</small>
                @if($creating)@error('code')<span class="admin-login-field-error" id="create-code-error">{{ $message }}</span>@enderror @endif
            </label>
            <label for="create-role-name">Tên vai trò *
                <input id="create-role-name" name="name" value="{{ $creating ? old('name') : '' }}" maxlength="100" placeholder="Tổ trưởng chuyên môn" required @if($creating && $errors->has('name'))aria-invalid="true" aria-describedby="create-name-error"@endif>
                @if($creating)@error('name')<span class="admin-login-field-error" id="create-name-error">{{ $message }}</span>@enderror @endif
            </label>
            <label for="create-role-description" class="identity-wide">Mô tả <small>Tối đa 255 ký tự</small><textarea id="create-role-description" name="description" class="editor-textarea short" maxlength="255" rows="2" @if($creating && $errors->has('description'))aria-invalid="true" aria-describedby="create-description-error"@endif>{{ $creating ? old('description') : '' }}</textarea>
                @if($creating)@error('description')<span class="admin-login-field-error" id="create-description-error">{{ $message }}</span>@enderror @endif
            </label>
            <div class="identity-actions identity-wide"><button class="button button-primary" type="submit">Tạo vai trò</button><span class="muted">Sau khi tạo, chọn các quyền cần cấp cho vai trò.</span></div>
        </form>
    </details>
    <div class="admin-form-grid identity-role-select"><label for="role-select-mobile">Chọn vai trò<select id="role-select-mobile" data-role-select>
        @foreach($roles as $role)<option value="{{ route('admin.roles.index', ['selected' => $role->id]) }}" @selected($selectedRole?->id === $role->id)>{{ $role->name }} · {{ $role->users_count }} người dùng</option>@endforeach
    </select></label></div>
    <div class="identity-columns">
        <aside class="panel identity-role-sidebar"><h2>Vai trò <span class="count-pill">{{ $roles->count() }}</span></h2>
            <nav class="identity-role-nav" aria-label="Danh sách vai trò">
                @forelse($roles as $role)
                    <a href="{{ route('admin.roles.index', ['selected' => $role->id]) }}" @if($selectedRole?->id === $role->id)aria-current="page"@endif>
                        <strong>{{ $role->name }} <span class="count-pill" aria-label="{{ $role->users_count }} người dùng">{{ $role->users_count }}</span></strong>
                        <span class="identity-meta">@if(in_array($role->code, ['admin', 'super-admin'], true))<span class="badge badge-soft">Hệ thống</span>@endif</span>
                    </a>
                @empty<p>Chưa có vai trò. Thêm vai trò ở trên để bắt đầu.</p>@endforelse
            </nav>
        </aside>
        <section class="panel role-permission-card" aria-labelledby="selected-role-title">
            @if($selectedRole)
                <header class="role-permission-card__header">
                    <div><div class="identity-meta">@if($isSuperAdmin)<span class="badge badge-amber">Super Admin (Bảo vệ)</span>@elseif($isSystemRole)<span class="badge badge-blue">Mặc định: Toàn quyền</span>@endif</div>
                        <h2 id="selected-role-title">{{ $selectedRole->name }}</h2><p>{{ $selectedRole->description ?: 'Chưa có mô tả.' }}</p><p>Đang gán cho <strong>{{ $selectedRole->users_count }}</strong> người dùng.</p>
                    </div>
                    @if(!$isSystemRole)
                        @php($revokedNames = $selectedRole->permissions->pluck('name')->implode(', '))
                        <form method="post" action="{{ route('admin.roles.destroy', $selectedRole) }}" data-confirm="Xóa vai trò {{ $selectedRole->name }} và gỡ khỏi {{ $selectedRole->users_count }} người dùng. Các quyền từ vai trò này sẽ bị thu hồi: {{ $revokedNames ?: 'chưa có quyền' }}. Quyền do vai trò khác cấp vẫn được giữ. Không thể hoàn tác việc xóa vai trò." data-confirm-title="Xóa vai trò {{ $selectedRole->name }}?" data-confirm-label="Xóa vai trò và gỡ {{ $selectedRole->users_count }} người dùng" data-pending-label="Đang xóa…">
                            @csrf @method('DELETE')<button class="button button-danger" type="submit">Xóa vai trò</button>
                        </form>
                    @endif
                </header>
                @if($isSystemRole)
                    <p class="role-permission-readonly">{{ $selectedRole->name }} luôn sở hữu toàn bộ quyền trong hệ thống và không thể chỉnh sửa.</p>
                @else
                    <form method="post" action="{{ route('admin.roles.update', $selectedRole) }}" class="role-permission-form" data-role-permissions data-member-count="{{ $selectedRole->users_count }}" data-role-name="{{ $selectedRole->name }}">
                        @csrf<input type="hidden" name="_identity_form" value="edit"><input type="hidden" name="permission_matrix" value="1">
                        @if(!$isSystemRole)
                            <div class="admin-form-grid">
                                <label for="role-edit-name">Tên vai trò *<input id="role-edit-name" name="name" value="{{ $editing ? old('name') : $selectedRole->name }}" required maxlength="100" @if($editing && $errors->has('name'))aria-invalid="true" aria-describedby="edit-name-error"@endif>
                                    @if($editing)@error('name')<span class="admin-login-field-error" id="edit-name-error">{{ $message }}</span>@enderror @endif
                                </label>
                                <label for="role-edit-description">Mô tả<input id="role-edit-description" name="description" value="{{ $editing ? old('description') : $selectedRole->description }}" maxlength="255" @if($editing && $errors->has('description'))aria-invalid="true" aria-describedby="edit-description-error"@endif><small>Tối đa 255 ký tự</small>
                                    @if($editing)@error('description')<span class="admin-login-field-error" id="edit-description-error">{{ $message }}</span>@enderror @endif
                                </label>
                            </div>
                        @endif
                        <div><h2>Gán quyền hệ thống</h2><p class="muted">Chỉ chọn quyền cần thiết cho công việc của vai trò.</p></div>
                        @error('permissions')<p class="admin-login-field-error">{{ $message }}</p>@enderror
                        <div class="role-permission-groups">
                            @foreach($permissionGroups as $group)
                                <fieldset class="role-permission-group" data-permission-group>
                                    <legend>{{ $group['label'] }}</legend><p>{{ $group['description'] }}</p>
                                    <div class="identity-actions"><button type="button" class="button button-quiet" data-group-check="true" aria-label="Chọn tất cả quyền {{ $group['label'] }}">Chọn tất cả</button><button type="button" class="button button-quiet" data-group-check="false" aria-label="Bỏ chọn quyền {{ $group['label'] }}">Bỏ chọn</button><small class="muted" data-permission-count aria-live="polite"></small></div>
                                    <div class="role-permission-options">
                                        @foreach($group['permissions'] as $permission)
                                            <label class="role-permission-option" for="role-perm-{{ $permission['id'] }}"><input id="role-perm-{{ $permission['id'] }}" type="checkbox" name="permissions[]" value="{{ $permission['id'] }}" @checked(in_array($permission['id'], $chosenPermissions, true)) @if($selectedRole->permissions->contains('id', $permission['id']))data-original-permission="{{ $permission['name'] }}"@endif><span><strong>{{ $permission['name'] }}</strong><small>{{ $permission['description'] }}</small></span></label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endforeach
                        </div>
                        <footer class="role-permission-form__footer"><span>Thay đổi áp dụng cho {{ $selectedRole->users_count }} người dùng đang có vai trò này.</span><button class="button button-primary" type="submit">Lưu quyền {{ $selectedRole->name }}</button></footer>
                    </form>
                @endif
            @else<h2 id="selected-role-title">Chưa có vai trò</h2><p>Thêm vai trò mới để bắt đầu phân quyền.</p>@endif
        </section>
    </div>
</div>
