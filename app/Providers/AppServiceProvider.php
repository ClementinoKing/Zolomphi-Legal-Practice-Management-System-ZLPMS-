<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\CompanyDriveFolder;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\CompanyDriveFolderPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MatterPolicy;
use App\Policies\PaymentPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        Gate::before(fn (User $user, string $ability): ?bool => $user->hasPermission($ability) ? true : null);
        Gate::define('access-administration', fn (User $user): bool => $user->hasPermission('administration.access'));
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(CompanyDriveFolder::class, CompanyDriveFolderPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(Matter::class, MatterPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
    }
}
