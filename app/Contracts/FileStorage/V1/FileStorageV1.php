<?php

namespace App\Contracts\FileStorage\V1;

interface FileStorageV1
{
    /**
     * Stores a private system-managed version and atomically makes it the active binding when one is requested.
     */
    public function storeManagedVersion(StoreManagedVersionRequest $request): StoredManagedVersion;

    /**
     * Moves an authorized workspace possession to the private trash while it continues consuming quota.
     */
    public function trashPossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult;

    /**
     * Restores an authorized workspace possession from the private trash before its purge deadline.
     */
    public function restorePossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult;

    /**
     * Irreversibly removes an authorized workspace possession without deleting content held by another possession.
     */
    public function releasePossession(FilePossessionOperationRequest $request): FilePossessionLifecycleResult;

    /**
     * Authorizes private bytes for an owner and returns a stream-only handle that never exposes a storage path.
     */
    public function authorizePrivateRead(FilePrivateReadRequest $request): AuthorizedPrivateFileRead;

    /**
     * Persists an extensible metadata value for a version reachable by an authorized owner possession.
     */
    public function storeVersionMetadata(StoreFileVersionMetadataRequest $request): void;

    /**
     * Reserves a validated internal relationship between a source version and a future derivative content.
     */
    public function reserveVersionDerivative(ReserveFileVersionDerivativeRequest $request): void;
}
