@if(session('status'))
    <div class="admin-toast" role="status" aria-live="polite" data-admin-toast data-admin-toast-timeout="6000"><span>{{ session('status') }}</span><button type="button" data-dismiss-admin-toast aria-label="Đóng thông báo">×</button></div>
@endif
@if($errors->any())
    <div class="admin-alert admin-alert-error" role="alert"><p>Kiểm tra lại thông tin:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
