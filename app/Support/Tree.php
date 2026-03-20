<?php

namespace App\Support;

class Tree
{
    private const ROOT = 'root';

    public static function rootPath(): string
    {
        return self::ROOT;
    }

    public static function childPath(string $parentPath, string $leg): string
    {
        return $parentPath . '.' . (strtoupper($leg) === 'LEFT' ? 'L' : 'R');
    }

    public static function depthFromPath(string $path): int
    {
        return max(0, substr_count($path, '.'));
    }

    public static function escapePath(string $path): string
    {
        return preg_quote($path, '/');
    }
}
