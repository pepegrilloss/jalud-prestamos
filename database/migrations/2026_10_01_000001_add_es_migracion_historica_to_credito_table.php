<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Retained as a no-op because historical settlement belongs to Gerencia bank loans.
    }

    public function down(): void
    {
        // Do not alter customer-credit data when rolling back migrations.
    }
};
