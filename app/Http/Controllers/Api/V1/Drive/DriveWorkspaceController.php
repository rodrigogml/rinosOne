<?php

namespace App\Http\Controllers\Api\V1\Drive;

use App\Domain\FileStorage\Exception\DriveWorkspaceCommandException;
use App\Domain\FileStorage\Exception\DriveWorkspaceProjectionException;
use App\Domain\FileStorage\Exception\DriveWorkspaceTargetException;
use App\Domain\FileStorage\Exception\DriveWorkspaceUploadException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\DriveItemSelectionRequest;
use App\Http\Requests\Drive\MoveDriveItemRequest;
use App\Http\Requests\Drive\ReleaseDriveItemSelectionRequest;
use App\Http\Requests\Drive\StoreDriveFolderRequest;
use App\Http\Requests\Drive\StoreDriveUploadRequest;
use App\Http\Requests\Drive\UpdateDriveFolderRequest;
use App\Models\FileStorage\StoredFilePossession;
use App\Models\FileStorage\WorkspaceFolder;
use App\Services\FileStorage\Drive\DriveWorkspaceCommandService;
use App\Services\FileStorage\Drive\DriveWorkspaceDownloadService;
use App\Services\FileStorage\Drive\DriveWorkspaceExportService;
use App\Services\FileStorage\Drive\DriveWorkspaceProjectionService;
use App\Services\FileStorage\Drive\DriveWorkspaceTargetResolver;
use App\Services\FileStorage\Drive\DriveWorkspaceUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveWorkspaceController extends Controller
{
    public function personalStoreFolder(StoreDriveFolderRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->createFolder($request->user(), $targets->personal($request->user()), $request->string('displayName')->toString(), $request->integer('parentFolderId') ?: null)), 201);
    }

    public function personalUpdateFolder(string $folderId, UpdateDriveFolderRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->renameFolder($request->user(), $targets->personal($request->user()), (int) $folderId, $request->string('displayName')->toString())));
    }

    public function personalMoveFolder(string $folderId, MoveDriveItemRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->moveFolder($request->user(), $targets->personal($request->user()), (int) $folderId, $request->integer('destinationFolderId') ?: null)));
    }

    public function personalMoveFile(string $possessionId, MoveDriveItemRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->fileResponse($commands->moveFile($request->user(), $targets->personal($request->user()), (int) $possessionId, $request->integer('destinationFolderId') ?: null)));
    }

    public function personalTrashItems(DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->trash($request->user(), $targets->personal($request->user()), $request->items()));
    }

    public function personalRestore(DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->restore($request->user(), $targets->personal($request->user()), $request->items()));
    }

    public function personalRelease(ReleaseDriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->release($request->user(), $targets->personal($request->user()), $request->items()));
    }

    public function personalUpload(StoreDriveUploadRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceUploadService $uploads): JsonResponse
    {
        return $this->respond(fn (): array => $this->uploadResponse($uploads->upload($request->user(), $targets->personal($request->user()), array_values($request->file('files', [])), $request->integer('parentFolderId') ?: null)));
    }

    public function personalRequestExport(DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->request($request->user(), $targets->personal($request->user()), $request->items())), 202);
    }

    public function personalExportStatus(string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->status($request->user(), $targets->personal($request->user()), $exportId)));
    }

    public function personalCancelExport(string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->cancel($request->user(), $targets->personal($request->user()), $exportId)));
    }

    public function personalDownloadExport(string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): StreamedResponse|JsonResponse
    {
        return $this->exportDownloadResponse(fn (): array => $exports->openDownload($request->user(), $targets->personal($request->user()), $exportId));
    }

    public function personalDownload(string $possessionId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceDownloadService $downloads): StreamedResponse|JsonResponse
    {
        return $this->downloadResponse(fn (): array => $downloads->open($request->user(), $targets->personal($request->user()), (int) $possessionId));
    }

    public function personalTree(Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->tree($request->user(), $targets->personal($request->user())));
    }

    public function personalRoot(Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->root($request->user(), $targets->personal($request->user())));
    }

    public function personalFolder(string $folderId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->folder($request->user(), $targets->personal($request->user()), (int) $folderId));
    }

    public function personalTrash(Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->trash($request->user(), $targets->personal($request->user())));
    }

    public function personalDetails(string $itemType, string $itemId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->details($request->user(), $targets->personal($request->user()), $itemType, (int) $itemId));
    }

    public function workTree(string $tenantId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->tree($request->user(), $targets->work($request->user(), (int) $tenantId)));
    }

    public function workRoot(string $tenantId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->root($request->user(), $targets->work($request->user(), (int) $tenantId)));
    }

    public function workFolder(string $tenantId, string $folderId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->folder($request->user(), $targets->work($request->user(), (int) $tenantId), (int) $folderId));
    }

    public function workTrash(string $tenantId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->trash($request->user(), $targets->work($request->user(), (int) $tenantId)));
    }

    public function workDetails(string $tenantId, string $itemType, string $itemId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceProjectionService $projections): JsonResponse
    {
        return $this->respond(fn (): array => $projections->details($request->user(), $targets->work($request->user(), (int) $tenantId), $itemType, (int) $itemId));
    }

    public function workStoreFolder(string $tenantId, StoreDriveFolderRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->createFolder($request->user(), $targets->work($request->user(), (int) $tenantId), $request->string('displayName')->toString(), $request->integer('parentFolderId') ?: null)), 201);
    }

    public function workUpdateFolder(string $tenantId, string $folderId, UpdateDriveFolderRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->renameFolder($request->user(), $targets->work($request->user(), (int) $tenantId), (int) $folderId, $request->string('displayName')->toString())));
    }

    public function workMoveFolder(string $tenantId, string $folderId, MoveDriveItemRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->folderResponse($commands->moveFolder($request->user(), $targets->work($request->user(), (int) $tenantId), (int) $folderId, $request->integer('destinationFolderId') ?: null)));
    }

    public function workMoveFile(string $tenantId, string $possessionId, MoveDriveItemRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->respond(fn (): array => $this->fileResponse($commands->moveFile($request->user(), $targets->work($request->user(), (int) $tenantId), (int) $possessionId, $request->integer('destinationFolderId') ?: null)));
    }

    public function workTrashItems(string $tenantId, DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->trash($request->user(), $targets->work($request->user(), (int) $tenantId), $request->items()));
    }

    public function workRestore(string $tenantId, DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->restore($request->user(), $targets->work($request->user(), (int) $tenantId), $request->items()));
    }

    public function workRelease(string $tenantId, ReleaseDriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceCommandService $commands): JsonResponse
    {
        return $this->commandResponse(fn () => $commands->release($request->user(), $targets->work($request->user(), (int) $tenantId), $request->items()));
    }

    public function workUpload(string $tenantId, StoreDriveUploadRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceUploadService $uploads): JsonResponse
    {
        return $this->respond(fn (): array => $this->uploadResponse($uploads->upload($request->user(), $targets->work($request->user(), (int) $tenantId), array_values($request->file('files', [])), $request->integer('parentFolderId') ?: null)));
    }

    public function workRequestExport(string $tenantId, DriveItemSelectionRequest $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->request($request->user(), $targets->work($request->user(), (int) $tenantId), $request->items())), 202);
    }

    public function workExportStatus(string $tenantId, string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->status($request->user(), $targets->work($request->user(), (int) $tenantId), $exportId)));
    }

    public function workCancelExport(string $tenantId, string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): JsonResponse
    {
        return $this->respond(fn (): array => $this->exportResponse($exports->cancel($request->user(), $targets->work($request->user(), (int) $tenantId), $exportId)));
    }

    public function workDownloadExport(string $tenantId, string $exportId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceExportService $exports): StreamedResponse|JsonResponse
    {
        return $this->exportDownloadResponse(fn (): array => $exports->openDownload($request->user(), $targets->work($request->user(), (int) $tenantId), $exportId));
    }

    public function workDownload(string $tenantId, string $possessionId, Request $request, DriveWorkspaceTargetResolver $targets, DriveWorkspaceDownloadService $downloads): StreamedResponse|JsonResponse
    {
        return $this->downloadResponse(fn (): array => $downloads->open($request->user(), $targets->work($request->user(), (int) $tenantId), (int) $possessionId));
    }

    /** @param callable(): array<string, mixed> $projection */
    /** @param callable(): array<string, mixed> $response */
    private function respond(callable $response, int $status = 200): JsonResponse
    {
        try {
            return response()->json($response(), $status);
        } catch (DriveWorkspaceTargetException) {
            return response()->json(['error' => ['code' => 'DRIVE_WORKSPACE_UNAVAILABLE', 'message' => 'O workspace solicitado não está disponível.']], 404);
        } catch (DriveWorkspaceProjectionException $exception) {
            $status = $exception->reasonCode === 'DRIVE_ACCESS_DENIED' ? 403 : 404;
            $message = $status === 403 ? 'Você não possui acesso a esta localização.' : 'A localização solicitada não está disponível.';

            return response()->json(['error' => ['code' => $exception->reasonCode, 'message' => $message]], $status);
        } catch (DriveWorkspaceCommandException $exception) {
            $status = $exception->reasonCode === 'DRIVE_ACCESS_DENIED' ? 403 : ($exception->reasonCode === 'DRIVE_LOCATION_NOT_FOUND' ? 404 : 422);

            return response()->json(['error' => ['code' => $exception->reasonCode, 'message' => 'Não foi possível concluir a operação solicitada.']], $status);
        } catch (DriveWorkspaceUploadException $exception) {
            return response()->json(['error' => ['code' => $exception->reasonCode, 'message' => 'O envio de arquivos não está disponível nesta instância.']], 422);
        }
    }

    /** @param callable(): void $command */
    private function commandResponse(callable $command): JsonResponse
    {
        try {
            $command();

            return response()->json(null, 204);
        } catch (DriveWorkspaceTargetException) {
            return response()->json(['error' => ['code' => 'DRIVE_WORKSPACE_UNAVAILABLE', 'message' => 'O workspace solicitado não está disponível.']], 404);
        } catch (DriveWorkspaceCommandException $exception) {
            $status = $exception->reasonCode === 'DRIVE_ACCESS_DENIED' ? 403 : ($exception->reasonCode === 'DRIVE_LOCATION_NOT_FOUND' ? 404 : 422);

            return response()->json(['error' => ['code' => $exception->reasonCode, 'message' => 'Não foi possível concluir a operação solicitada.']], $status);
        }
    }

    /** @return array<string, mixed> */
    private function folderResponse(WorkspaceFolder $folder): array
    {
        return ['id' => $folder->id, 'kind' => 'folder', 'displayName' => $folder->displayName, 'parentFolderId' => $folder->idParentFolder];
    }

    /** @return array<string, mixed> */
    private function fileResponse(StoredFilePossession $possession): array
    {
        return ['id' => $possession->id, 'kind' => 'file', 'displayName' => $possession->displayName, 'parentFolderId' => $possession->idWorkspaceFolder];
    }

    /** @param list<array<string, mixed>> $results */
    private function uploadResponse(array $results): array
    {
        return [
            'results' => $results,
            'storedCount' => count(array_filter($results, fn (array $result): bool => $result['state'] === 'STORED')),
            'rejectedCount' => count(array_filter($results, fn (array $result): bool => $result['state'] === 'REJECTED')),
        ];
    }

    /** @return array<string, mixed> */
    private function exportResponse(\App\Models\FileStorage\WorkspaceExport $export): array
    {
        return ['exportId' => $export->publicId, 'state' => $export->state, 'expiresAt' => $export->expiresAt?->toISOString()];
    }

    /** @param callable(): array{stream: resource, displayName: string, detectedMimeType: string} $download */
    private function downloadResponse(callable $download): StreamedResponse|JsonResponse
    {
        try {
            $file = $download();

            return response()->streamDownload(static function () use ($file): void {
                fpassthru($file['stream']);
                fclose($file['stream']);
            }, $file['displayName'], ['Content-Type' => $file['detectedMimeType'], 'X-Content-Type-Options' => 'nosniff']);
        } catch (DriveWorkspaceTargetException) {
            return response()->json(['error' => ['code' => 'DRIVE_WORKSPACE_UNAVAILABLE', 'message' => 'O workspace solicitado não está disponível.']], 404);
        } catch (DriveWorkspaceProjectionException) {
            return response()->json(['error' => ['code' => 'DRIVE_LOCATION_NOT_FOUND', 'message' => 'O arquivo solicitado não está disponível.']], 404);
        }
    }

    /** @param callable(): array{stream: resource, displayName: string} $download */
    private function exportDownloadResponse(callable $download): StreamedResponse|JsonResponse
    {
        try {
            $export = $download();
            return response()->streamDownload(static function () use ($export): void { fpassthru($export['stream']); fclose($export['stream']); }, $export['displayName'], ['Content-Type' => 'application/zip', 'X-Content-Type-Options' => 'nosniff']);
        } catch (DriveWorkspaceTargetException|DriveWorkspaceCommandException) {
            return response()->json(['error' => ['code' => 'DRIVE_EXPORT_UNAVAILABLE', 'message' => 'A exportação solicitada não está disponível.']], 404);
        }
    }
}
