<?php

use App\Models\Client;
use App\Models\ClientContactPoint;
use App\Models\ClientDuplicateReview;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

function duplicateReviewAdministrator(): User
{
    return grantAllPracticePermissions(User::factory()->create());
}

/** @return array<string, mixed> */
function duplicateReviewPayload(array $overrides = []): array
{
    return array_replace([
        'type' => 'organization',
        'name' => 'Lakeview Foods Malawi Limited',
        'status' => 'active',
    ], $overrides);
}

test('duplicate candidate checks identify normalized name email and phone matches', function () {
    $administrator = duplicateReviewAdministrator();
    $existing = Client::factory()->create([
        'type' => 'organization',
        'full_name' => null,
        'organization_name' => 'Lakeview Foods Malawi Limited',
        'email' => 'LEGAL@LAKEVIEW.EXAMPLE',
        'phone' => '+265 999 010 020',
    ]);

    $this->actingAs($administrator)
        ->postJson(route('clients.duplicate-candidates'), [
            'type' => 'organization',
            'name' => 'lakeview-foods malawi limited',
            'email' => 'legal@lakeview.example',
            'phone' => '+265-999-010-020',
            'alternate_phone' => '',
        ])
        ->assertOk()
        ->assertJsonPath('candidates.0.id', $existing->id)
        ->assertJsonPath('candidates.0.matched_fields', ['name', 'email', 'phone']);
});

test('client creation requires review and retains confirmed duplicate records as linked profiles', function () {
    $administrator = duplicateReviewAdministrator();
    $this->actingAs($administrator);
    $existing = Client::factory()->create([
        'type' => 'organization',
        'full_name' => null,
        'organization_name' => 'Lakeview Foods Malawi Limited',
        'email' => 'legal@lakeview.example',
        'phone' => '+265 999 010 020',
    ]);

    $this->post(route('clients.store'), duplicateReviewPayload([
        'name' => 'lakeview-foods malawi limited',
        'email' => 'LEGAL@LAKEVIEW.EXAMPLE',
        'phone' => '+265-999-010-020',
    ]))->assertSessionHasErrors('duplicate_decisions');

    expect(Client::query()->where('normalized_name', 'lakeviewfoodsmalawilimited')->count())->toBe(1);

    $this->post(route('clients.store'), duplicateReviewPayload([
        'name' => 'lakeview-foods malawi limited',
        'email' => 'LEGAL@LAKEVIEW.EXAMPLE',
        'phone' => '+265-999-010-020',
        'duplicate_decisions' => [[
            'candidate_id' => $existing->id,
            'decision' => 'duplicate',
        ]],
    ]))->assertRedirect(route('clients.index', absolute: false));

    $newClient = Client::query()->where('id', '>', $existing->id)->latest('id')->firstOrFail();
    $review = ClientDuplicateReview::query()->sole();

    expect($newClient->id)->not->toBe($existing->id);
    expect($newClient->email_contact_id)->toBe($existing->email_contact_id);
    expect($newClient->phone_contact_id)->toBe($existing->phone_contact_id);
    expect($review->decision)->toBe('duplicate');
    expect($review->reviewed_by_user_id)->toBe($administrator->id);
    expect($review->reviewed_at)->not->toBeNull();

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('clients', function (Collection $clients) use ($existing, $newClient): bool {
                return collect($clients)
                    ->whereIn('id', [$existing->id, $newClient->id])
                    ->every(fn (array $client): bool => count($client['linked_clients']) === 1
                        && $client['linked_clients'][0]['decision'] === 'duplicate');
            }));
});

test('different clients can share contact points after a negative duplicate review', function () {
    $this->actingAs(duplicateReviewAdministrator());
    $existing = Client::factory()->create([
        'type' => 'organization',
        'full_name' => null,
        'organization_name' => 'Chisomo Legal Services',
        'phone' => '+265 999 555 100',
    ]);

    $this->post(route('clients.store'), duplicateReviewPayload([
        'name' => 'Mphatso Legal Services',
        'phone' => '+265 (999) 555-100',
        'duplicate_decisions' => [[
            'candidate_id' => $existing->id,
            'decision' => 'not_duplicate',
        ]],
    ]))->assertRedirect(route('clients.index', absolute: false));

    $newClient = Client::query()->where('organization_name', 'Mphatso Legal Services')->sole();

    expect($newClient->phone_contact_id)->toBe($existing->phone_contact_id);
    expect(ClientContactPoint::query()->where('type', 'phone')->where('normalized_value', '+265999555100')->count())->toBe(1);
    expect(ClientDuplicateReview::query()->sole()->decision)->toBe('not_duplicate');
});

test('alternate phone values are normalized and updates can retain a shared current contact', function () {
    $this->actingAs(duplicateReviewAdministrator());
    $existing = Client::factory()->create([
        'type' => 'organization',
        'full_name' => null,
        'organization_name' => 'Nyika Digital Services',
        'phone' => '+265 888 010 200',
    ]);
    $existingPhoneContactId = $existing->phone_contact_id;

    $this->postJson(route('clients.duplicate-candidates'), [
        'type' => 'individual',
        'name' => 'Chikondi Banda',
        'email' => '',
        'phone' => '',
        'alternate_phone' => '+265-888-010-200',
    ])->assertOk()
        ->assertJsonPath('candidates.0.id', $existing->id)
        ->assertJsonPath('candidates.0.matched_fields', ['phone']);

    $this->patch(route('clients.update', $existing), duplicateReviewPayload([
        'name' => 'Nyika Digital Services',
        'email' => 'CONTACT@NYIKA.EXAMPLE',
        'phone' => '+265 888 010 200',
    ]))->assertRedirect(route('clients.index', absolute: false));

    $existing->refresh();
    expect($existing->phone_contact_id)->toBe($existingPhoneContactId);
    expect($existing->email)->toBe('CONTACT@NYIKA.EXAMPLE');
});

test('contact points enforce normalized uniqueness in the database', function () {
    $contact = ClientContactPoint::query()->create([
        'type' => 'email',
        'value' => 'legal@example.test',
        'normalized_value' => 'legal@example.test',
    ]);

    expect(fn () => ClientContactPoint::query()->create([
        'type' => 'email',
        'value' => 'LEGAL@EXAMPLE.TEST',
        'normalized_value' => $contact->normalized_value,
    ]))->toThrow(QueryException::class);
});

test('duplicate candidate endpoint requires client creation access', function () {
    $this->postJson(route('clients.duplicate-candidates'), [
        'type' => 'organization',
        'name' => 'Chisomo Holdings',
    ])->assertUnauthorized();

    $this->actingAs(User::factory()->create())
        ->postJson(route('clients.duplicate-candidates'), [
            'type' => 'organization',
            'name' => 'Chisomo Holdings',
        ])->assertForbidden();
});
