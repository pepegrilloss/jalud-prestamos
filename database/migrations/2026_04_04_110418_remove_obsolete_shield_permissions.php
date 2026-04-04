<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $unwanted = ['restore', 'restore_any', 'replicate', 'reorder', 'force_delete', 'force_delete_any', 'delete_any'];
        $unwantedFull = ['page_SelectSede', 'page_EvaluacionDeCredito'];

        $perms = Permission::all();
        foreach($perms as $p) {
            if (in_array($p->name, $unwantedFull)) {
                $p->delete();
                continue;
            }

            foreach($unwanted as $u) {
                if(str_starts_with($p->name, $u.'_')) {
                    $p->delete();
                    break;
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration for deleted permissions
    }
};
