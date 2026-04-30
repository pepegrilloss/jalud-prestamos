<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->unsignedBigInteger('ProveedorID')->nullable()->after('NombreProveedor');
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->unsignedBigInteger('ProveedorID')->nullable()->after('NombreProveedor');
        });

        DB::statement('UPDATE Compra SET ProveedorID = (SELECT ProveedorID FROM Proveedor WHERE Proveedor.Nombre = Compra.NombreProveedor LIMIT 1) WHERE NombreProveedor IS NOT NULL');
        DB::statement('UPDATE Gasto SET ProveedorID = (SELECT ProveedorID FROM Proveedor WHERE Proveedor.Nombre = Gasto.NombreProveedor LIMIT 1) WHERE NombreProveedor IS NOT NULL');

        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn('NombreProveedor');
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->dropColumn('NombreProveedor');
        });
    }

    public function down(): void
    {
        Schema::table('Compra', function (Blueprint $table) {
            $table->string('NombreProveedor', 400)->nullable()->after('ProveedorID');
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->string('NombreProveedor', 400)->nullable()->after('ProveedorID');
        });

        DB::statement('UPDATE Compra SET NombreProveedor = (SELECT Nombre FROM Proveedor WHERE Proveedor.ProveedorID = Compra.ProveedorID LIMIT 1) WHERE ProveedorID IS NOT NULL');
        DB::statement('UPDATE Gasto SET NombreProveedor = (SELECT Nombre FROM Proveedor WHERE Proveedor.ProveedorID = Gasto.ProveedorID LIMIT 1) WHERE ProveedorID IS NOT NULL');

        Schema::table('Compra', function (Blueprint $table) {
            $table->dropColumn('ProveedorID');
        });

        Schema::table('Gasto', function (Blueprint $table) {
            $table->dropColumn('ProveedorID');
        });
    }
};
