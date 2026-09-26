<?php

namespace App\Contracts\FileStorage\V1;

use Closure;
use RuntimeException;

class AuthorizedPrivateFileRead
{
    /**
     * @param  Closure(): resource  $streamFactory
     */
    public function __construct(
        public readonly int $contentId,
        public readonly string $detectedMimeType,
        public readonly ?string $declaredExtension,
        public readonly int $logicalSizeBytes,
        private readonly Closure $streamFactory,
    ) {}

    /**
     * Opens an authorized private byte stream without disclosing the underlying filesystem location.
     *
     * @return resource
     */
    public function openStream()
    {
        $stream = ($this->streamFactory)();

        if (! is_resource($stream)) {
            throw new RuntimeException('The authorized file content is unavailable.');
        }

        return $stream;
    }
}
