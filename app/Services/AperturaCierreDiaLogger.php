<?php

namespace App\Services;

class AperturaCierreDiaLogger
{
    private $logFile;

    public function __construct()
    {
        $this->logFile = storage_path('logs/apertura_cierre_dia.log');
        
        // Crear el archivo si no existe
        if (!file_exists($this->logFile)) {
            file_put_contents($this->logFile, "[LOG INICIALIZADO] " . now()->format('Y-m-d H:i:s') . "\n");
        }
    }

    public function info($message, array $context = [])
    {
        $this->write('INFO', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->write('WARNING', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->write('ERROR', $message, $context);
    }

    public function success($message, array $context = [])
    {
        $this->write('SUCCESS', $message, $context);
    }

    private function write($level, $message, array $context = [])
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        
        $logLine = "[{$timestamp}] [{$level}] {$message}";
        if ($contextStr) {
            $logLine .= " | DATA: {$contextStr}";
        }
        $logLine .= "\n";

        file_put_contents($this->logFile, $logLine, FILE_APPEND);
    }

    public function getLogs($limit = 100)
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($lines, -$limit);
    }

    public function clearLogs()
    {
        file_put_contents($this->logFile, "[LOG LIMPIADO] " . now()->format('Y-m-d H:i:s') . "\n");
    }
}
