<?php
print_r(\App\Models\Credito::whereHas('proposicion', function($q) { $q->where('CodigoCredito', 'C-000028'); })->first()->toArray());
