<?php

namespace VistiqLearner\Http\Controllers\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use VistiqCore\Services\System\AuditService;
use VistiqLearner\Http\Controllers\Controller;

class AccountPasswordController extends Controller
{
    public function edit(): mixed
    {
        return view('shared.demo-page', ['page' => ['title' => 'Đổi mật khẩu', 'view' => 'pages.learner.authentication.account-password', 'section' => 'profile', 'shell' => 'auth', 'slug' => 'account-password', 'show_heading' => false]]);
    }

    public function update(Request $request, AuditService $audit): mixed
    {
        $data = $request->validate(['current_password' => 'required|current_password:web', 'password' => ['required', 'string', 'different:current_password', 'confirmed', Password::min(8)->letters()->numbers()]]);
        DB::transaction(function () use ($request, $data, $audit): void {
            $user = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60), 'session_version' => $user->session_version + 1])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $request->user()->setRawAttributes($user->getAttributes(), true);
            $audit->record('account.password.change', 'user', $user->id);
        });
        $request->session()->regenerate();
        $request->session()->put('user_session_version', $request->user()->session_version);

        return redirect()->route('profile.show')->with('status', 'Đã đổi mật khẩu và thu hồi các phiên đăng nhập khác.');
    }
}
