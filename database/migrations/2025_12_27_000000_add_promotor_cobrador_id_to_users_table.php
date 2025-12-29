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
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('PromotorCobradorID')->nullable()->after('id');
            $table->foreign('PromotorCobradorID')->references('PromotorCobradorID')->on('PromotorCobrador')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['PromotorCobradorID']);
            $table->dropColumn('PromotorCobradorID');
        });
    }
};
