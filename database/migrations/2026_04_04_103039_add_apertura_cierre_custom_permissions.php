<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'abrir_dia_apertura', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'cerrar_dia_apertura', 'guard_name' => 'web']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::whereIn('name', ['abrir_dia_apertura', 'cerrar_dia_apertura'])->delete();
    }
};
