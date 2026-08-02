<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('documents.view') || $user->hasPermission('company-files.view');
    }

    public function view(User $user, Document $document): bool
    {
        if (in_array($document->visibility, ['internal', 'private'], true)
            && $document->matter_id !== null
            && ! $user->hasPermission('documents.view.private')) {
            return false;
        }

        if ($document->matter_id !== null) {
            return $user->hasPermission('documents.view')
                && $document->matter !== null
                && $user->can('view', $document->matter);
        }

        if (! $user->hasPermission('company-files.view')) {
            return false;
        }

        return $user->hasPermission('matters.view.all')
            || ($document->client !== null && $document->client->matters()->visibleTo($user)->exists());
    }

    public function upload(User $user): bool
    {
        return $user->hasPermission('documents.upload');
    }

    public function update(User $user, Document $document): bool
    {
        return $this->upload($user) && $this->view($user, $document);
    }
}
