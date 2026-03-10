<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Filament');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

$regexes = [
    // 1. Remove methods that only check permissions
    [
        'pattern' => '/[ \t]*public static function can(ViewAny|View|Create|Edit|Delete|DeleteAny|Restore|RestoreAny|ForceDelete|ForceDeleteAny)\([^)]*\): bool\s*\{\s*return auth\(\)->user\(\)->can\([^)]+\);\s*\}/m',
        'replacement' => ''
    ],
    // 2. Change methods that only check AperturaCierreDia
    [
        'pattern' => '/(public static function can(Create|Edit|Delete|DeleteAny|Restore|ForceDelete)\([^)]*\): bool\s*\{\s*)return AperturaCierreDia::estaAbierto\(\);(\s*\})/m',
        'replacement' => "$1return parent::can$2(...func_get_args()) && \App\Models\AperturaCierreDia::estaAbierto();$3"
    ],
    // 3. Change methods that check AperturaCierreDia AND Permission
    [
        'pattern' => '/(public static function can(Create|Edit|Delete|DeleteAny|Restore|ForceDelete)\([^)]*\): bool\s*\{\s*)return AperturaCierreDia::estaAbierto\(\)\s*&&\s*auth\(\)->user\(\)->can\([^)]+\);(\s*\})/m',
        'replacement' => "$1return parent::can$2(...func_get_args()) && \App\Models\AperturaCierreDia::estaAbierto();$3"
    ],
    // 4. In actions, remove `->visible(fn() => auth()->user()->can('...'))` completely
    [
        'pattern' => '/\s*->visible\s*\(\s*fn\s*\([^)]*\)\s*=>\s*auth\(\)->user\(\)->can\([^)]+\)\s*\)/m',
        'replacement' => ''
    ],
    // 5. In actions, remove permission part from AperturaCierreDia
    [
        'pattern' => '/->visible\s*\(\s*fn\s*\([^)]*\)\s*=>\s*AperturaCierreDia::estaAbierto\(\)\s*&&\s*auth\(\)->user\(\)->can\([^)]+\)\s*\)/m',
        'replacement' => '->visible(fn() => \App\Models\AperturaCierreDia::estaAbierto())'
    ],
];

foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $original = $content;

    foreach ($regexes as $rule) {
        $content = preg_replace($rule['pattern'], $rule['replacement'], $content);
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: $path\n";
    }
}
echo "Done.\n";
