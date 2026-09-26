<?php

namespace App\Domain\FileStorage\Compression;

class FileStorageCompressionPolicy
{
    /**
     * Determines whether configured MIME type or extension rules allow GZIP for logical content.
     */
    public function shouldCompress(string $mimeType, ?string $extension): bool
    {
        foreach (config('file-storage.compression.rules', []) as $rule) {
            if (($rule['encoding'] ?? null) !== 'GZIP') {
                continue;
            }

            $mimeMatches = $this->matchesMimeType($mimeType, $rule['mimeTypes'] ?? []);
            $extensionMatches = $this->matchesExtension($extension, $rule['extensions'] ?? []);

            if ($mimeMatches || $extensionMatches) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $patterns */
    private function matchesMimeType(string $mimeType, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if ($pattern === $mimeType || (str_ends_with($pattern, '/*') && str_starts_with($mimeType, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $extensions */
    private function matchesExtension(?string $extension, array $extensions): bool
    {
        if ($extension === null) {
            return false;
        }

        return in_array(strtolower($extension), array_map('strtolower', array_filter($extensions, 'is_string')), true);
    }
}
