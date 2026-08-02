<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Http\Requests\UpdateClientSettingsRequest;
use App\Models\GeneralSetting;
use App\Services\PracticeRecordSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientSettingsController extends Controller
{
    public function __construct(
        private readonly PracticeRecordSettings $settings,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('clients/settings', [
            'settings' => $this->settings->clientValues(),
        ]);
    }

    public function update(UpdateClientSettingsRequest $request): RedirectResponse
    {
        $settings = GeneralSetting::query()->firstOrNew();
        $this->settings->updateClient($settings, $request->validated());
        $this->audit->handle($request->user(), 'client.settings_updated', 'Updated client settings.');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Client settings saved.',
        ]);

        return to_route('clients.settings.edit');
    }
}
