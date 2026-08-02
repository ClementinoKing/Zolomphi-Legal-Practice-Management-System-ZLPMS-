<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Http\Requests\UpdateMatterSettingsRequest;
use App\Models\GeneralSetting;
use App\Services\PracticeRecordSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MatterSettingsController extends Controller
{
    public function __construct(
        private readonly PracticeRecordSettings $settings,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('matters/settings', [
            'settings' => $this->settings->matterValues(),
        ]);
    }

    public function update(UpdateMatterSettingsRequest $request): RedirectResponse
    {
        $settings = GeneralSetting::query()->firstOrNew();
        $this->settings->updateMatter($settings, $request->validated());
        $this->audit->handle($request->user(), 'matter.settings_updated', 'Updated matter settings.');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Matter settings saved.',
        ]);

        return to_route('matters.settings.edit');
    }
}
