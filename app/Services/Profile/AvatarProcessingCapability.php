<?php

namespace App\Services\Profile;

use App\Domain\Profile\Exception\AvatarProcessingUnavailableException;

class AvatarProcessingCapability
{
    /** @var list<string> */
    private const REQUIRED_FUNCTIONS = [
        'imagecreatefromjpeg',
        'imagecreatefrompng',
        'imagecreatefromwebp',
        'imagejpeg',
        'imagepng',
        'imagewebp',
    ];

    public function isAvailable(): bool
    {
        return $this->missingFunctions() === [];
    }

    /** @return list<string> */
    public function missingFunctions(): array
    {
        return array_values(array_filter(
            self::REQUIRED_FUNCTIONS,
            fn (string $function): bool => ! $this->functionExists($function),
        ));
    }

    public function assertAvailable(): void
    {
        $missingFunctions = $this->missingFunctions();

        if ($missingFunctions !== []) {
            throw new AvatarProcessingUnavailableException($missingFunctions);
        }
    }

    protected function functionExists(string $function): bool
    {
        return function_exists($function);
    }
}
