<?php

namespace VistiqAdmin\Http\Controllers\Admin\Identity\Authentication;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use VistiqAdmin\Http\Controllers\StaticPageController;
use VistiqAdmin\Http\Requests\Admin\Identity\Authentication\PasswordRequest;
use VistiqCore\Models\User;
use VistiqCore\Services\System\SettingsService;
use VistiqLearner\Http\Controllers\Authentication\Mail\ResetPasswordEmail;

class AuthController extends StaticPageController
{
    public function loginForm(): View
    {
        return $this->renderScreen('admin-login', ['title' => 'Đăng nhập quản trị', 'frame' => 'AUTH05', 'view' => 'pages.admin.identity.authentication.screens.login', 'section' => 'auth', 'description' => 'Đăng nhập Admin Studio.', 'variant' => 'admin-login']);
    }

    public function authenticate(PasswordRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['email'] = mb_strtolower(trim($credentials['email']));
        if (! Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Thông tin đăng nhập chưa chính xác.'])->onlyInput('email');
        }
        if (! $request->user()->isAdmin()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Tài khoản không có quyền quản trị.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        $request->session()->put('admin_session_version', $request->user()->session_version);
        $request->session()->forget('admin_otp_verified');

        return $this->redirectToAdminSurface($request);
    }

    public function passwordForm(Request $request, ?string $token = null): View
    {
        return $this->renderScreen('admin-password', ['title' => 'Khôi phục mật khẩu quản trị', 'frame' => '', 'view' => 'pages.admin.identity.authentication.screens.password', 'section' => 'auth', 'description' => 'Khôi phục mật khẩu quản trị.', 'token' => $token, 'email' => $request->input('email')]);
    }

    public function sendReset(PasswordRequest $request): RedirectResponse
    {
        try {
            app(SettingsService::class)->configureMail();
            if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
                return back()->withErrors(['email' => 'Dịch vụ email chưa sẵn sàng. Vui lòng liên hệ quản trị viên để cấu hình gửi email.'])->onlyInput('email');
            }
            ResetPasswordEmail::configure();
            Password::sendResetLink($request->safe()->only('email'));
        } catch (TransportExceptionInterface $exception) {
            return back()->withErrors(['email' => 'Chưa thể gửi email khôi phục. Vui lòng thử lại sau ít phút hoặc liên hệ quản trị viên.'])->onlyInput('email');
        }

        return back()->with('status', 'Nếu tài khoản tồn tại, hướng dẫn khôi phục đã được gửi.')->onlyInput('email');
    }

    public function resetPassword(PasswordRequest $request): RedirectResponse
    {
        $status = Password::reset($request->validated(), function (User $user, string $password): void {
            $user->forceFill(['password' => Hash::make($password), 'session_version' => $user->session_version + 1])->setRememberToken(Str::random(60));
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });

        return $status === Password::PasswordReset ? redirect()->route('admin.login')->with('status', 'Đã đổi mật khẩu. Vui lòng đăng nhập lại trên các thiết bị.') : back()->withErrors(['email' => 'Liên kết này không hợp lệ hoặc đã hết hạn. Hãy yêu cầu liên kết mới.'])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->forget(['admin_session_version', 'admin_otp_verified', 'admin.intended']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function redirectToAdminSurface(Request $request): RedirectResponse
    {
        $intended = $request->session()->pull('url.intended');
        $path = is_string($intended) ? parse_url($intended, PHP_URL_PATH) : null;

        if (is_string($path) && preg_match('#^/(?:admin|api/v1/admin)(?:/|$)#', $path) === 1) {
            $query = parse_url($intended, PHP_URL_QUERY);
            $target = $path.($query ? '?'.$query : '');

            return redirect()->to($target);
        }

        return redirect()->route('admin.dashboard');
    }
}
