<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Paso 1: Agregar columna username como nullable primero
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
        });

        // Paso 2: Generar usernames temporales para usuarios existentes
        $users = DB::table('users')->get();
        foreach ($users as $user) {
            // Genera username: primeras letras del nombre + ID
            $nameParts = explode(' ', $user->name);
            $username = strtoupper(substr($nameParts[0], 0, 1));
            if (isset($nameParts[1])) {
                $username .= strtoupper(substr($nameParts[1], 0, 1));
            }
            $username .= $user->id;
            
            DB::table('users')
                ->where('id', $user->id)
                ->update(['username' => $username]);
        }

        // Paso 3: Ahora hacer la columna NOT NULL
        DB::statement("ALTER TABLE users ALTER COLUMN username NVARCHAR(255) NOT NULL");

        // Paso 4: Agregar constraint unique
        DB::statement("ALTER TABLE users ADD CONSTRAINT UQ_users_username UNIQUE (username)");

        // Paso 5: Hacer email nullable
        DB::statement("ALTER TABLE users ALTER COLUMN email NVARCHAR(255) NULL");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            DB::statement("ALTER TABLE users DROP CONSTRAINT UQ_users_username");
            $table->dropColumn('username');
            DB::statement("ALTER TABLE users ALTER COLUMN email NVARCHAR(255) NOT NULL");
        });
    }
};