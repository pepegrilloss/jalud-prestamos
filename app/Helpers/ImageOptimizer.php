<?php

namespace App\Helpers;

class ImageOptimizer
{
    private const QUALITY = 80;
    private const MAX_WIDTH = 1280;
    private const MAX_HEIGHT = 1600;

    public static function optimize(string $fullPath): array
    {
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            return ['path' => $fullPath, 'extension' => $extension];
        }

        [$width, $height, $type] = @getimagesize($fullPath);
        if (!$type) {
            return ['path' => $fullPath, 'extension' => $extension];
        }

        $src = self::crearImagenDesde($fullPath, $type);
        if (!$src) {
            return ['path' => $fullPath, 'extension' => $extension];
        }

        [$newWidth, $newHeight] = self::calcularDimensiones($width, $height);
        $dst = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($src);

        $newPath = preg_replace('/\.(' . implode('|', ['jpg', 'jpeg', 'png', 'gif']) . ')$/i', '.webp', $fullPath);

        imagewebp($dst, $newPath, self::QUALITY);
        imagedestroy($dst);

        if ($fullPath !== $newPath && file_exists($fullPath)) {
            unlink($fullPath);
        }

        return [
            'path' => $newPath,
            'extension' => 'webp',
        ];
    }

    private static function crearImagenDesde(string $path, int $type): ?object
    {
        return match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private static function calcularDimensiones(int $width, int $height): array
    {
        if ($width <= self::MAX_WIDTH && $height <= self::MAX_HEIGHT) {
            return [$width, $height];
        }

        $ratio = min(self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        return [(int) round($width * $ratio), (int) round($height * $ratio)];
    }
}
