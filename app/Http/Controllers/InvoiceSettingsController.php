<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditEvent;
use App\Http\Requests\UpdateInvoiceSettingsRequest;
use App\Models\GeneralSetting;
use App\Services\InvoiceSettings;
use App\Services\InvoiceTemplateLogoManager;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceSettingsController extends Controller
{
    public function __construct(
        private readonly InvoiceSettings $settings,
        private readonly InvoiceTemplateLogoManager $logos,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('invoices/settings', [
            'settings' => $this->settings->values(),
            'firm' => GeneralSetting::query()->first(['firm_name', 'tax_id', 'currency']),
            'logos' => $this->logos->options(),
        ]);
    }

    public function update(UpdateInvoiceSettingsRequest $request): RedirectResponse
    {
        $generalSettings = GeneralSetting::query()->firstOrNew();
        $attributes = $request->validated();
        $logoFile = $request->file('logo_file');
        unset($attributes['logo_file']);

        if ($logoFile !== null) {
            $attributes['template']['logo'] = $this->logos->store($logoFile, $request->user());
        } else {
            $attributes['template']['logo'] = $this->logos->resolve($attributes['template']['logo'] ?? null);
        }

        $this->settings->update($generalSettings, $attributes);
        $this->audit->handle($request->user(), 'invoice.settings_updated', 'Updated invoice settings.');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Invoice settings saved.',
        ]);

        return to_route('invoices.settings.edit');
    }
}
