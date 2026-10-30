<?php

namespace VistiqAdmin\Console\Commands;

use Database\Admin\Seeders\AdminCatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use VistiqAdmin\Models\User;
use VistiqCore\Models\Role;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create {email} {--name=} {--password-stdin}';

    protected $description = 'Tạo hoặc cấp quyền Super Admin cho tài khoản, không dùng mật khẩu mặc định.';

    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }
        $user = User::where('email', $email)->first();
        $password = null;
        if (! $user) {
            $password = $this->option('password-stdin') ? rtrim(stream_get_contents(STDIN), "\r\n") : $this->secret('Mật khẩu mới (ít nhất 10 ký tự, gồm chữ và số)');
            if (Validator::make(['password' => $password], ['password' => 'required|string|min:10|regex:/[A-Za-z]/|regex:/[0-9]/'])->fails()) {
                $this->error('Mật khẩu chưa đủ mạnh.');

                return self::FAILURE;
            }
        }
        DB::transaction(function () use (&$user, $email, $password): void {
            (new AdminCatalogSeeder)->run();
            $user ??= User::create(['email' => $email, 'name' => $this->option('name') ?: 'Super Admin', 'password' => $password]);
            $user->forceFill(['status' => 'active'])->save();
            $user->roles()->syncWithoutDetaching([Role::where('code', 'super-admin')->value('id')]);
        });
        $this->info('Đã thiết lập Super Admin.');

        return self::SUCCESS;
    }
}
