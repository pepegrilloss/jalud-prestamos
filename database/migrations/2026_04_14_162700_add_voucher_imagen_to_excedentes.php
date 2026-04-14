<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            if (!Schema::hasColumn('excedentes', 'VoucherImagen')) {
                $table->string('VoucherImagen', 500)->nullable()->after('Observaciones');
            }
        });
    }

    public function down(): void
    {
        Schema::table('excedentes', function (Blueprint $table) {
            if (Schema::hasColumn('excedentes', 'VoucherImagen')) {
                $table->dropColumn('VoucherImagen');
            }
        });
    }
};
