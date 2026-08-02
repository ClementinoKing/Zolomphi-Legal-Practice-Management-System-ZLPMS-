<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyDriveDocumentRequest;
use App\Models\CompanyDriveFolder;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyFilesController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 401);

        $folders = CompanyDriveFolder::query()
            ->select(['id', 'client_id', 'name', 'library'])
            ->whereNotNull('client_id')
            ->whereHas('client', fn (Builder $client): Builder => $client->whereHas('matters', fn (Builder $matters): Builder => $matters->whereIn('id', Matter::query()->visibleTo($user)->select('id'))))
            ->when($user->hasPermission('matters.view.all'), fn ($query) => $query->orWhereNotNull('client_id'))
            ->withCount('documents')
            ->with([
                'client:id,client_number',
                'documents:id,company_drive_folder_id,name,original_filename,mime_type,size_bytes,updated_at',
            ])
            ->orderBy('library')
            ->orderBy('name')
            ->get();

        $folderPayload = static fn (CompanyDriveFolder $folder): array => [
            'id' => $folder->id,
            'name' => $folder->name,
            'clientNumber' => $folder->client->client_number,
            'documentCount' => (int) $folder->documents_count,
            'documents' => $folder->documents
                ->map(static fn (Document $document): array => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'originalFilename' => $document->original_filename,
                    'mimeType' => $document->mime_type,
                    'sizeBytes' => $document->size_bytes,
                    'updatedAt' => $document->updated_at?->toIso8601String(),
                    'downloadUrl' => route('files.download', $document),
                ])
                ->all(),
        ];

        return Inertia::render('files/index', [
            'clientFolders' => $folders
                ->where('library', CompanyDriveFolder::LibraryClients)
                ->map($folderPayload)
                ->values()
                ->all(),
            'organizationFolders' => $folders
                ->where('library', CompanyDriveFolder::LibraryOrganizations)
                ->map($folderPayload)
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreCompanyDriveDocumentRequest $request): RedirectResponse
    {
        $folder = CompanyDriveFolder::query()->findOrFail($request->integer('folder_id'));
        abort_unless($request->user()->can('upload', $folder), 403);
        $disk = config('filesystems.company_drive_disk');

        if (! is_string($disk)) {
            throw new \LogicException('The company-drive filesystem disk must be a string.');
        }

        foreach ($request->file('documents', []) as $file) {
            $path = $file->store('company-drive/folders/'.$folder->id, $disk);

            Document::query()->create([
                'client_id' => $folder->client_id,
                'company_drive_folder_id' => $folder->id,
                'uploaded_by_user_id' => $request->user()->id,
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'category' => 'company-drive',
                'visibility' => 'internal',
                'disk' => $disk,
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        }

        return back();
    }

    public function download(Document $document): StreamedResponse
    {
        abort_unless(auth()->user()?->can('view', $document), 403);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_filename,
        );
    }

    public function preview(Document $document): StreamedResponse
    {
        abort_unless(auth()->user()?->can('view', $document), 403);

        abort_unless(in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_filename,
            ['Content-Type' => $document->mime_type],
            ResponseHeaderBag::DISPOSITION_INLINE,
        );
    }
}
