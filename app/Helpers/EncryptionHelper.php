<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Crypt;

class EncryptionHelper
{
    /**
     * Detecta si un valor está encriptado
     * 
     * Laravel encripta en formato JSON con base64, típicamente comienza con:
     * - eyJ (base64 para "{")
     * - base64: (prefijo explícito)
     */
    public static function isEncrypted($value): bool
    {
        if (!is_string($value) || empty($value)) {
            return false;
        }

        // Cheques rápidos
        if (strpos($value, 'base64:') === 0) {
            return true;
        }

        if (strpos($value, 'eyJ') === 0) {
            return true;
        }

        return false;
    }

    /**
     * Desencripta un valor de forma segura
     * Si no está encriptado, lo retorna tal cual
     */
    public static function decrypt($value, $default = null)
    {
        if (!$value) {
            return $default;
        }

        try {
            // Si no parece estar encriptado, retorna tal cual
            if (!self::isEncrypted($value)) {
                return $value;
            }

            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            \Log::warning('SEGURIDAD - Error desencriptando valor', [
                'error' => $e->getMessage(),
                'first_chars' => substr($value, 0, 10)
            ]);
            return $default;
        }
    }

    /**
     * Encripta un valor si no está ya encriptado
     */
    public static function encryptIfNeeded($value): ?string
    {
        if (!$value) {
            return null;
        }

        // Si ya está encriptado, no lo encriptes de nuevo
        if (self::isEncrypted($value)) {
            return $value;
        }

        try {
            return Crypt::encryptString($value);
        } catch (\Exception $e) {
            \Log::error('SEGURIDAD - Error encriptando valor', [
                'error' => $e->getMessage()
            ]);
            return $value; // Retorna sin encriptar si falla
        }
    }
}
