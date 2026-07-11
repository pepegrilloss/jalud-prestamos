<?php

use App\Models\CalendarioNoMoroso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('calendario_no_morosos', 'Tipo')) {
            Schema::table('calendario_no_morosos', function (Blueprint $table) {
                $table->string('Tipo', 30)
                    ->default(CalendarioNoMoroso::TIPO_NO_LABORABLE)
                    ->after('Descripcion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('calendario_no_morosos', 'Tipo')) {
            Schema::table('calendario_no_morosos', function (Blueprint $table) {
                $table->dropColumn('Tipo');
            });
        }
    }
};
