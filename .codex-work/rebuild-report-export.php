<?php

require __DIR__.'/../vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

$root = dirname(__DIR__);
$oldCode = file_get_contents(__DIR__.'/original-report-export.php');
$currentCode = file_get_contents($root.'/app/Http/Controllers/ReporteExportController.php');

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$oldStmts = $parser->parse($oldCode);
$oldTokens = $parser->getTokens();
$currentStmts = $parser->parse($currentCode);

$traverser = new NodeTraverser;
$traverser->addVisitor(new CloningVisitor);
$newStmts = $traverser->traverse($oldStmts);

$findClass = static function (array $stmts): Node\Stmt\Class_ {
    foreach ($stmts as $stmt) {
        if ($stmt instanceof Node\Stmt\Namespace_) {
            foreach ($stmt->stmts as $namespaceStmt) {
                if ($namespaceStmt instanceof Node\Stmt\Class_) {
                    return $namespaceStmt;
                }
            }
        }
    }

    throw new RuntimeException('Class not found.');
};

$oldClass = $findClass($newStmts);
$currentClass = $findClass($currentStmts);
$oldMethods = [];
foreach ($oldClass->getMethods() as $method) {
    $oldMethods[$method->name->toString()] = $method;
}

$changedMethods = [
    'carteraExcel',
    'eficienciaCobranzaExcel',
    'baseCreditosEficiencia',
    'basePagosEficiencia',
    'clasificarClientesEficiencia',
    'clasificarRegistrosEficiencia',
    'eficienciaCobranzaDetalleExcel',
    'proyeccionExcel',
    'carteraGeneralExcel',
];

$rebuiltMethods = [];
foreach ($currentClass->getMethods() as $method) {
    $name = $method->name->toString();
    $rebuiltMethods[] = in_array($name, $changedMethods, true)
        ? $method
        : ($oldMethods[$name] ?? $method);
}
$oldClass->stmts = $rebuiltMethods;

$output = (new Standard)->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
file_put_contents(__DIR__.'/targeted-report-export.php', $output);

