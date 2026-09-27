<?php

$avatarMimeTypes = array_values(array_filter(array_map(
    static fn (string $mimeType): string => trim($mimeType),
    explode(',', (string) env('PROFILE_AVATAR_ALLOWED_MIME_TYPES', 'image/jpeg,image/png,image/webp')),
)));

return [
    'avatar' => [
        'allowedMimeTypes' => $avatarMimeTypes,
        'maximumSizeBytes' => (int) env('PROFILE_AVATAR_MAXIMUM_SIZE_BYTES', 10 * 1024 * 1024),
        'minimumDimensionPixels' => (int) env('PROFILE_AVATAR_MINIMUM_DIMENSION_PIXELS', 400),
        'outputDimensionPixels' => (int) env('PROFILE_AVATAR_OUTPUT_DIMENSION_PIXELS', 400),
    ],
];
