<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ForcePasswordChangeRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ForcePasswordChangeController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('auth/force-password-change', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(ForcePasswordChangeRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password')->toString(),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Password updated. Your account is ready to use.']);

        return to_route('dashboard');
    }
}
