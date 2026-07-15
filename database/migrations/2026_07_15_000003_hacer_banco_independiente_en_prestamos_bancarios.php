<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table) {
            $table->string('Banco', 100)->nullable()->after('CuentaTesoreriaID')->index();
        });

        DB::table('tesoreria_prestamos_bancarios')
            ->orderBy('PrestamoBancarioID')
            ->each(function (object $prestamo): void {
                $banco = DB::table('tesoreria_cuentas')
                    ->where('CuentaTesoreriaID', $prestamo->CuentaTesoreriaID)
                    ->value('Banco');

                if ($banco) {
                    DB::table('tesoreria_prestamos_bancarios')
                        ->where('PrestamoBancarioID', $prestamo->PrestamoBancarioID)
                        ->update(['Banco' => $banco]);
                }
            });

        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table) {
            $table->unsignedBigInteger('CuentaTesoreriaID')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tesoreria_prestamos_bancarios', function (Blueprint $table) {
            $table->dropIndex(['Banco']);
            $table->dropColumn('Banco');
        });
    }
};
