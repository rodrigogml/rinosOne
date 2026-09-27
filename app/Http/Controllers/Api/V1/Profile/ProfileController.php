<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Domain\FileStorage\Exception\FileStorageAccessException;
use App\Domain\Profile\Avatar\AvatarCrop;
use App\Http\Requests\Profile\StoreAvatarRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Services\Profile\UserProfileService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController
{
    public function __construct(private readonly UserProfileService $profiles) {}

    public function show(): JsonResponse
    {
        return response()->json($this->profiles->describe(request()->user()));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        return response()->json($this->profiles->updateDisplayName(
            $request->user(),
            $request->string('displayName')->toString(),
        ));
    }

    public function avatar(): StreamedResponse
    {
        try {
            $avatar = $this->profiles->readAvatar(request()->user());
        } catch (FileStorageAccessException) {
            abort(404);
        }

        return response()->stream(function () use ($avatar): void {
            $stream = $avatar->openStream();

            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $avatar->detectedMimeType,
            'Content-Length' => (string) $avatar->logicalSizeBytes,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function storeAvatar(StoreAvatarRequest $request): JsonResponse
    {
        return response()->json($this->profiles->replaceAvatar(
            $request->user(),
            $request->file('image'),
            new AvatarCrop(
                x: (float) $request->input('cropX'),
                y: (float) $request->input('cropY'),
                size: (float) $request->input('cropSize'),
            ),
        ), 201);
    }

    public function destroy(): JsonResponse
    {
        $this->profiles->removeAvatar(request()->user());

        return response()->json(null, 204);
    }
}
