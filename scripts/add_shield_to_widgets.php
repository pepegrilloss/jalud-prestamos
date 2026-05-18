<?php
$dir = new RecursiveDirectoryIterator(__DIR__ . '/app/Filament/Widgets');
$ite = new RecursiveIteratorIterator($dir);
$files = new RegexIterator($ite, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $original = $content;

    // Check if it's a class
    if (preg_match('/class\s+([a-zA-Z0-9_]+)\s+extends\s+.*?(?:\r\n|\n)\{/sw', $content, $matches)) {

        // Add namespace for Trait if not exists
        if (strpos($content, 'use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;') === false) {
            $content = preg_replace('/(namespace\s+[^;]+;)/', "$1\n\nuse BezhanSalleh\FilamentShield\Traits\HasWidgetShield;", $content);
        }

        // Add use trait inside class if not exists
        if (strpos($content, 'use HasWidgetShield;') === false) {
            $content = preg_replace('/(class\s+[a-zA-Z0-9_]+\s+extends\s+.*?(?:\r\n|\n)\{)/', "$1\n    use HasWidgetShield;\n", $content);
        }
    }

    if ($content !== $original) {
        file_put_contents($path, $content);
        echo "Updated: $path\n";
    }
}
echo "Done.\n";
