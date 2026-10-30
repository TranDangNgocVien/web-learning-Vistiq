<?php

namespace VistiqAdmin\Http\Controllers\Admin\Identity\Authentication;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use VistiqAdmin\Http\Controllers\StaticPageController;
use VistiqAdmin\Http\Requests\Admin\Identity\Authentication\OtpRequest;
use VistiqCore\Services\System\SettingsService;

class AdminOtpController extends StaticPageController
{
    public function show(Request $request): mixed
    {
        abort_unless($request->user()->isAdmin(), 403);

        return $this->renderScreen('admin-verify', ['title' => 'Xác thực quản trị', 'frame' => '', 'view' => 'pages.admin.identity.authentication.screens.verify', 'section' => 'auth', 'description' => 'Xác thực OTP quản trị.']);
    }

    public function send(Request $request, SettingsService $settings): mixed
    {
        abort_unless($request->user()->isAdmin(), 403);
        $settings->configureMail();
        $email = $settings->get('email');
        abort_unless($email['mailer'] === 'smtp' && filled($email['host']), 422, 'Cần cấu hình email SMTP trước khi gửi OTP.');
        $code = (string) random_int(100000, 999999);
        DB::table('admin_otp_challenges')->updateOrInsert(['session_hash' => hash('sha256', $request->session()->getId())], ['user_id' => $request->user()->id, 'code_hash' => Hash::make($code), 'tries' => 0, 'expires_at' => now()->addMinutes(5), 'consumed_at' => null, 'created_at' => now(), 'updated_at' => now()]);
        Mail::raw('Mã xác thực Vistiq Admin: '.$code.'. Hết hạn sau 5 phút.', fn ($message) => $message->to($request->user()->email)->subject('Vistiq Admin OTP'));

        return back()->with('status', 'Đã gửi mã xác thực qua email.');
    }

    public function verify(OtpRequest $request): mixed
    {
        $valid = DB::transaction(function () use ($request): bool {
            $query = DB::table('admin_otp_challenges')->where('session_hash', hash('sha256', $request->session()->getId()))->where('user_id', $request->user()->id);
            $challenge = $query->lockForUpdate()->first();
            if (! $challenge || $challenge->consumed_at || $challenge->tries >= 5 || Carbon::parse($challenge->expires_at)->lte(now())) {
                return false;
            }
            $query->increment('tries');
            if (! Hash::check($request->validated('code'), $challenge->code_hash)) {
                return false;
            }
            $query->update(['consumed_at' => now()]);

            return true;
        });
        if (! $valid) {
            return back()->withErrors(['code' => 'Mã không hợp lệ, hết hạn hoặc vượt số lần thử.']);
        }
        $request->session()->put('admin_otp_verified', true);

        return redirect()->route('admin.dashboard');
    }
}
