<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(
                ['name' => 'descargar_excel_clientes', 'guard_name' => 'web'],
                ['name' => 'descargar_excel_clientes', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
            \Illuminate\Support\Facades\DB::table('permissions')->updateOrInsert(
                ['name' => 'descargar_pdf_clientes', 'guard_name' => 'web'],
                ['name' => 'descargar_pdf_clientes', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            \Illuminate\Support\Facades\DB::table('permissions')
                ->whereIn('name', ['descargar_excel_clientes', 'descargar_pdf_clientes'])
                ->where('guard_name', 'web')
                ->delete();
        }
    }
};
