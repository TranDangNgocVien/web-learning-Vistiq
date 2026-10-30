<?php

namespace VistiqLearner\Http\Controllers\Authentication;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use VistiqCore\Models\User as DomainUser;
use VistiqCore\Services\System\SettingsService;
use VistiqLearner\Http\Controllers\Authentication\Mail\ResetPasswordEmail;
use VistiqLearner\Http\Controllers\StaticPageController;
use VistiqLearner\Http\Requests\Authentication\ForgotPasswordRequest;
use VistiqLearner\Http\Requests\Authentication\LoginRequest;
use VistiqLearner\Http\Requests\Authentication\RegisterRequest;
use VistiqLearner\Http\Requests\Authentication\ResetPasswordRequest;
use VistiqLearner\Models\User;

class AuthController extends StaticPageController
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    private const SCREENS = [
        'welcome-demo' => ['title' => 'Mở demo Vistiq English', 'frame' => '00', 'view' => 'pages.auth.cover', 'section' => 'auth', 'description' => 'Một không gian học tiếng Anh rõ ràng, tập trung và vừa sức.'],
        'login' => ['title' => 'Đăng nhập', 'frame' => 'AUTH01', 'view' => 'pages.learner.authentication.screens.login', 'section' => 'auth', 'description' => 'Chào mừng bạn trở lại Vistiq English.', 'variant' => 'login'],
        'register' => ['title' => 'Tạo tài khoản', 'frame' => 'AUTH02', 'view' => 'pages.learner.authentication.screens.register', 'section' => 'auth', 'description' => 'Bắt đầu lưu lại hành trình học tiếng Anh của bạn.', 'variant' => 'register'],
        'forgot-password' => ['title' => 'Quên mật khẩu', 'frame' => 'AUTH03', 'view' => 'pages.learner.authentication.screens.forgot', 'section' => 'auth', 'description' => 'Nhập email để nhận hướng dẫn đặt lại mật khẩu.', 'variant' => 'forgot'],
        'email-sent' => ['title' => 'Kiểm tra email', 'frame' => 'AUTH04', 'view' => 'pages.learner.authentication.screens.email-sent', 'section' => 'auth', 'description' => 'Hướng dẫn đặt lại mật khẩu đã được gửi.', 'variant' => 'email-sent'],
        'reset-password' => ['title' => 'Đặt lại mật khẩu', 'frame' => 'AUTH07', 'view' => 'pages.learner.authentication.screens.reset-password', 'section' => 'auth', 'description' => 'Tạo mật khẩu mới và an toàn cho tài khoản.', 'variant' => 'reset'],
        'password-updated' => ['title' => 'Đổi mật khẩu thành công', 'frame' => 'AUTH08', 'view' => 'pages.learner.authentication.screens.updated', 'section' => 'auth', 'description' => 'Bạn có thể đăng nhập bằng mật khẩu mới.', 'variant' => 'updated'],
    ];

    public function show(string $screen): View
    {
        return $this->renderKnownScreen($screen, self::SCREENS);
    }

    public function loginForm(): View
    {
        return $this->renderKnownScreen('login', self::SCREENS);
    }

    public function registerForm(): View
    {
        return $this->renderKnownScreen('register', self::SCREENS);
    }

    public function forgotPasswordForm(): View
    {
        return $this->renderKnownScreen('forgot-password', self::SCREENS);
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        $page = [
            ...self::SCREENS['reset-password'],
            'slug' => 'reset-password',
            'shell' => 'auth',
            'token' => $token,
            'email' => $request->query('email', ''),
        ];

        return view('shared.demo-page', ['page' => $page]);
    }

    public function authenticate(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $throttleKey = $this->loginThrottleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::LOGIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors(['email' => "Bạn đã thử đăng nhập quá nhiều lần. Hãy thử lại sau {$seconds} giây."])
                ->onlyInput('email');
        }

        if (! Auth::attempt([...$credentials, 'status' => 'active'], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, self::LOGIN_DECAY_SECONDS);

            return back()
                ->withErrors(['email' => 'Email hoặc mật khẩu không chính xác.'])
                ->onlyInput('email');
        }

        if ($request->user()?->isAdmin()) {
            Auth::logout();
            $request->session()->forget('url.intended');

            return back()
                ->withErrors(['email' => 'Tài khoản quản trị hãy đăng nhập tại cổng Admin.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $request->session()->put('user_session_version', $request->user()->session_version);

        return redirect()->intended(route('home'));
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $attributes['name'] = trim((string) $attributes['name']);
        $attributes['email'] = Str::lower(trim((string) $attributes['email']));

        $user = User::create($attributes);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function sendResetLink(ForgotPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        try {
            app(SettingsService::class)->configureMail();
            if (in_array(config('mail.default'), ['log', 'array'], true) && ! app()->environment('testing')) {
                return back()->withErrors(['email' => 'Dịch vụ email chưa sẵn sàng. Vui lòng liên hệ quản trị viên để cấu hình gửi email.'])->onlyInput('email');
            }
            ResetPasswordEmail::configure();
            $status = Password::sendResetLink($credentials);
        } catch (TransportExceptionInterface $exception) {
            return back()->withErrors(['email' => 'Chưa thể gửi email khôi phục. Vui lòng thử lại sau ít phút hoặc liên hệ quản trị viên.'])->onlyInput('email');
        }

        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
            return redirect()->route('password.sent')->with('status', 'Nếu email này có tài khoản Vistiq, hướng dẫn đặt lại mật khẩu sẽ được gửi đến hộp thư của bạn.');
        }

        return back()
            ->withErrors(['email' => 'Chưa thể xử lý yêu cầu khôi phục. Vui lòng thử lại sau ít phút.'])
            ->onlyInput('email');
    }

    public function resetPassword(ResetPasswordRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $status = Password::reset(
            $credentials,
            function (DomainUser $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'session_version' => $user->session_version + 1,
                ])->setRememberToken(Str::random(60));

                $user->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Mật khẩu đã được cập nhật. Bạn có thể đăng nhập bằng mật khẩu mới.');
        }

        return back()
            ->withErrors(['email' => 'Liên kết này không hợp lệ hoặc đã hết hạn. Hãy yêu cầu liên kết mới.'])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->forget(['user_session_version', 'learner.intended']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function loginThrottleKey(Request $request): string
    {
        return Str::transliterate(Str::lower(trim((string) $request->input('email'))).'|'.$request->ip());
    }
}
