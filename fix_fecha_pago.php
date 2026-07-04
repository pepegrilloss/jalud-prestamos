<?php
require 'vendor/autoload.php';
$a = require_once 'bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

DB::table('pago')->where('PagoID', 111268)->update(['FechaPago' => '2026-07-02 23:59:59']);
echo "PagoID=111268 actualizado a 2026-07-02\n";
