<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(['code' => 'results.export'], ['name' => 'Xuất báo cáo']);
        $permission = DB::table('permissions')->where('code', 'results.export')->value('id');
        foreach (DB::table('roles')->whereIn('code', ['admin', 'super-admin'])->pluck('id') as $role) {
            DB::table('permission_role')->insertOrIgnore(['permission_id' => $permission, 'role_id' => $role]);
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('code', 'results.export')->value('id');
        DB::table('permission_role')->where('permission_id', $permission)->delete();
        DB::table('permissions')->where('id', $permission)->delete();
    }
};
