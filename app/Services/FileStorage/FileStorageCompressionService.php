<?php

namespace App\Services\FileStorage;

use App\Domain\FileStorage\Compression\FileStorageCompressionPolicy;
use App\Domain\FileStorage\Compression\PreparedFileStorageRepresentation;
use App\Domain\FileStorage\Exception\FileStorageIngestionException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FileStorageCompressionService
{
    public function __construct(private readonly FileStorageCompressionPolicy $policy) {}

    /**
     * Produces a GZIP staging representation only when policy permits and compressed bytes save storage space.
     *
     * @param  array{logicalSha256: string, logicalSizeBytes: int, detectedMimeType: string, declaredExtension: ?string}  $descriptor
     */
    public function prepare(Filesystem $disk, string $logicalStagingKey, array $descriptor): PreparedFileStorageRepresentation
    {
        if (! $this->policy->shouldCompress($descriptor['detectedMimeType'], $descriptor['declaredExtension'])) {
            return new PreparedFileStorageRepresentation(
                encoding: 'IDENTITY',
                stagingKey: $logicalStagingKey,
                storedSha256: $descriptor['logicalSha256'],
                storedSizeBytes: $descriptor['logicalSizeBytes'],
                compressed: false,
            );
        }

        $source = $disk->readStream($logicalStagingKey);

        if (! is_resource($source)) {
            throw new FileStorageIngestionException('The staged file storage content cannot be compressed.');
        }

        $temporary = tmpfile();

        if ($temporary === false) {
            fclose($source);

            throw new FileStorageIngestionException('The file storage compression workspace is unavailable.');
        }

        try {
            $context = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
            $hash = hash_init('sha256');
            $storedSizeBytes = 0;

            while (! feof($source)) {
                $chunk = fread($source, 8192);

                if ($chunk === false) {
                    throw new FileStorageIngestionException('The staged file storage content cannot be compressed.');
                }

                $compressedChunk = deflate_add($context, $chunk, ZLIB_NO_FLUSH);

                if ($compressedChunk === false || fwrite($temporary, $compressedChunk) !== strlen($compressedChunk)) {
                    throw new FileStorageIngestionException('The staged file storage content cannot be compressed.');
                }

                hash_update($hash, $compressedChunk);
                $storedSizeBytes += strlen($compressedChunk);
            }

            $tail = deflate_add($context, '', ZLIB_FINISH);

            if ($tail === false || fwrite($temporary, $tail) !== strlen($tail)) {
                throw new FileStorageIngestionException('The staged file storage content cannot be compressed.');
            }

            hash_update($hash, $tail);
            $storedSizeBytes += strlen($tail);

            if ($storedSizeBytes >= $descriptor['logicalSizeBytes']) {
                return new PreparedFileStorageRepresentation(
                    encoding: 'IDENTITY',
                    stagingKey: $logicalStagingKey,
                    storedSha256: $descriptor['logicalSha256'],
                    storedSizeBytes: $descriptor['logicalSizeBytes'],
                    compressed: false,
                );
            }

            rewind($temporary);
            $compressedStagingKey = '.file-storage-staging/'.Str::uuid().'.gzip';

            if ($disk->writeStream($compressedStagingKey, $temporary) !== true) {
                throw new FileStorageIngestionException('The compressed file storage content cannot be staged.');
            }

            return new PreparedFileStorageRepresentation(
                encoding: 'GZIP',
                stagingKey: $compressedStagingKey,
                storedSha256: hash_final($hash),
                storedSizeBytes: $storedSizeBytes,
                compressed: true,
            );
        } finally {
            fclose($source);
            fclose($temporary);
        }
    }
}
