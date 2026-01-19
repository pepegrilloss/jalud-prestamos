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
        Schema::table('ProposicionCredito', function (Blueprint $table) {
            if (!Schema::hasColumn('ProposicionCredito', 'SaldoPendiente')) {
                $table->decimal('SaldoPendiente', 12, 2)->nullable()->after('MontoTotalPagar');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ProposicionCredito', function (Blueprint $table) {
            if (Schema::hasColumn('ProposicionCredito', 'SaldoPendiente')) {
                $table->dropColumn('SaldoPendiente');
            }
        });
    }
};
