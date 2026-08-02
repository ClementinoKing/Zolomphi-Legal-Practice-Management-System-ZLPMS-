<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class InvoiceTemplateLogoManager
{
    /**
     * @return array<int, array{id: string, name: string, source: string, preview_url: string}>
     */
    public function options(): array
    {
        return [
            ...$this->systemOptions(),
            ...Document::query()
                ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
                ->latest('updated_at')
                ->get(['id', 'name', 'original_filename'])
                ->map(fn (Document $document): array => $this->documentOption($document))
                ->all(),
        ];
    }

    /**
     * @param  array{id: string}|null  $selection
     * @return array{id: string, name: string, source: string, preview_url: string}|null
     */
    public function resolve(?array $selection): ?array
    {
        if ($selection === null) {
            return null;
        }

        foreach ($this->systemOptions() as $option) {
            if ($option['id'] === $selection['id']) {
                return $option;
            }
        }

        if (! str_starts_with($selection['id'], 'document:')) {
            return null;
        }

        $documentId = (int) substr($selection['id'], strlen('document:'));

        if ($documentId < 1) {
            return null;
        }

        $document = Document::query()
            ->whereKey($documentId)
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->first();

        return $document === null ? null : $this->documentOption($document);
    }

    /**
     * @return array{id: string, name: string, source: string, preview_url: string}
     */
    public function store(UploadedFile $file, User $user): array
    {
        $disk = config('filesystems.company_drive_disk');

        if (! is_string($disk)) {
            throw new \LogicException('The company-drive filesystem disk must be a string.');
        }

        $path = $file->store('invoice-templates/logos', $disk);
        $document = Document::query()->create([
            'uploaded_by_user_id' => $user->id,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'category' => 'invoice-template-logo',
            'visibility' => 'internal',
            'disk' => $disk,
            'path' => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'metadata' => ['purpose' => 'invoice-template-logo'],
        ]);

        return $this->documentOption($document);
    }

    /**
     * @return array<int, array{id: string, name: string, source: string, preview_url: string}>
     */
    private function systemOptions(): array
    {
        return [
            [
                'id' => 'system:zolomphi-logo',
                'name' => 'Zolomphi & Co. primary logo',
                'source' => 'System library',
                'preview_url' => '/logos/Zolomphi Logo.svg',
            ],
            [
                'id' => 'system:zolomphi-logo-alt',
                'name' => 'Zolomphi & Co. alternate logo',
                'source' => 'System library',
                'preview_url' => '/logos/Zolomphi Logo Alt.svg',
            ],
            [
                'id' => 'system:zolomphi-favicon',
                'name' => 'Zolomphi & Co. mark',
                'source' => 'System library',
                'preview_url' => '/logos/Zolomphi Favicon.svg',
            ],
        ];
    }

    /**
     * @return array{id: string, name: string, source: string, preview_url: string}
     */
    private function documentOption(Document $document): array
    {
        return [
            'id' => 'document:'.$document->id,
            'name' => $document->name,
            'source' => 'Company drive',
            'preview_url' => route('files.preview', $document),
        ];
    }
}
