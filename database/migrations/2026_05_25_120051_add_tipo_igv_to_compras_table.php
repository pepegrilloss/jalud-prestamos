<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->string('TipoIGV', 20)->default('GRAVADO')->after('Total');
        });
    }

    public function down(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn('TipoIGV');
        });
    }
};
