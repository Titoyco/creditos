<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = DB::table('users')->where('username', 'superuser')->first();
        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update([
                'name'        => 'Super Administrador',
                'password'    => Hash::make('Pablobar1979'),
                'is_admin'    => 1,
                'id_sucursal' => 1,
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('users')->insert([
                'name'        => 'Super Administrador',
                'username'    => 'superuser',
                'password'    => Hash::make('Pablobar1979'),
                'is_admin'    => 1,
                'id_sucursal' => 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('username', 'superuser')->delete();
    }
};
