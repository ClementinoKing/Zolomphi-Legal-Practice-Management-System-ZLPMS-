<?php

namespace Database\Seeders;

use App\Actions\Clients\GenerateClientNumber;
use App\Actions\Matters\GenerateMatterNumber;
use App\Models\Appointment;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterType;
use App\Models\User;
use App\Services\ClientContactManager;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

class PracticeDemoSeeder extends Seeder
{
    /** @var list<array{client: array{type: string, name: string, email: string, phone: string, city: string}, matter: array{title: string, description: string, priority: string, budget_amount: int, deadline_in_days: int}}> */
    private const Records = [
        [
            'client' => ['type' => 'organization', 'name' => 'Mphamvu Renewables Limited', 'email' => 'practice-demo-mphamvu@zolomphi.example', 'phone' => '+265 888 410 101', 'city' => 'Lilongwe'],
            'matter' => ['title' => 'Solar project land rights review', 'description' => 'Review lease, access, and land-rights documentation for a proposed solar project.', 'priority' => 'high', 'budget_amount' => 4200000, 'deadline_in_days' => 21],
        ],
        [
            'client' => ['type' => 'organization', 'name' => 'Lakeview Foods Malawi Limited', 'email' => 'practice-demo-lakeview@zolomphi.example', 'phone' => '+265 888 410 102', 'city' => 'Blantyre'],
            'matter' => ['title' => 'Supplier recovery and settlement strategy', 'description' => 'Assess recovery options and negotiate settlement terms for unpaid supplier obligations.', 'priority' => 'urgent', 'budget_amount' => 2850000, 'deadline_in_days' => 14],
        ],
        [
            'client' => ['type' => 'individual', 'name' => 'Chikondi Mbewe', 'email' => 'practice-demo-chikondi@zolomphi.example', 'phone' => '+265 888 410 103', 'city' => 'Mzuzu'],
            'matter' => ['title' => 'Estate administration and probate application', 'description' => 'Prepare probate documents and coordinate the administration of estate assets.', 'priority' => 'normal', 'budget_amount' => 1650000, 'deadline_in_days' => 30],
        ],
        [
            'client' => ['type' => 'organization', 'name' => 'Kasupe Logistics Limited', 'email' => 'practice-demo-kasupe@zolomphi.example', 'phone' => '+265 888 410 104', 'city' => 'Lilongwe'],
            'matter' => ['title' => 'Employment policy and disciplinary review', 'description' => 'Review workplace policies and advise on a pending senior-staff disciplinary process.', 'priority' => 'high', 'budget_amount' => 2100000, 'deadline_in_days' => 18],
        ],
        [
            'client' => ['type' => 'organization', 'name' => 'Nyika Digital Services Limited', 'email' => 'practice-demo-nyika@zolomphi.example', 'phone' => '+265 888 410 105', 'city' => 'Zomba'],
            'matter' => ['title' => 'Technology licensing and data protection advice', 'description' => 'Prepare software licensing terms and advise on data protection obligations for a new platform.', 'priority' => 'normal', 'budget_amount' => 3600000, 'deadline_in_days' => 25],
        ],
    ];

    /** @var list<array{record_index: int, title: string, description: string, type: string, location: string, starts_in_days: int, hour: int}> */
    private const Events = [
        ['record_index' => 0, 'title' => 'Solar project instructions meeting', 'description' => 'Confirm the project timeline and land-document requirements with the client.', 'type' => 'meeting', 'location' => 'Zolomphi Boardroom', 'starts_in_days' => 2, 'hour' => 9],
        ['record_index' => 1, 'title' => 'Supplier recovery strategy session', 'description' => 'Review the debtor schedule and agree the settlement approach.', 'type' => 'meeting', 'location' => 'Video conference', 'starts_in_days' => 4, 'hour' => 11],
        ['record_index' => 2, 'title' => 'Probate document review', 'description' => 'Review the probate instruction pack before filing.', 'type' => 'reminder', 'location' => 'Zolomphi Boardroom', 'starts_in_days' => 6, 'hour' => 10],
        ['record_index' => 3, 'title' => 'Employment disciplinary hearing', 'description' => 'Attend the scheduled disciplinary hearing and record next actions.', 'type' => 'hearing', 'location' => 'Client offices', 'starts_in_days' => 8, 'hour' => 14],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $matterTypes = MatterType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
            $attorneys = User::attorneys()->orderBy('name')->get();

            if ($matterTypes->isEmpty()) {
                throw new LogicException('At least one active matter type is required before seeding practice demo records.');
            }

            if ($attorneys->isEmpty()) {
                throw new LogicException('At least one active attorney is required before seeding practice demo records.');
            }

            $matters = collect(self::Records)
                ->map(fn (array $record, int $index): Matter => $this->seedMatter(
                    $record,
                    $matterTypes,
                    $attorneys,
                    $index,
                ));

            foreach (self::Events as $event) {
                $matter = $matters->get($event['record_index']);

                if (! $matter instanceof Matter) {
                    continue;
                }

                $this->seedEvent($matter, $event);
            }
        });
    }

    /**
     * @param  array{client: array{type: string, name: string, email: string, phone: string, city: string}, matter: array{title: string, description: string, priority: string, budget_amount: int, deadline_in_days: int}}  $record
     * @param  Collection<int, MatterType>  $matterTypes
     * @param  Collection<int, User>  $attorneys
     */
    private function seedMatter(array $record, Collection $matterTypes, Collection $attorneys, int $index): Matter
    {
        $client = $this->seedClient($record['client']);
        $existingMatter = Matter::query()
            ->where('client_id', $client->id)
            ->where('title', $record['matter']['title'])
            ->first();

        if ($existingMatter !== null) {
            return $existingMatter;
        }

        $matterType = $matterTypes->values()->get($index % $matterTypes->count());
        $attorney = $attorneys->values()->get($index % $attorneys->count());

        if (! $matterType instanceof MatterType || ! $attorney instanceof User) {
            throw new LogicException('Unable to resolve an attorney and matter type for practice demo records.');
        }

        $billingType = $matterType->default_billing_type;
        $matter = Matter::create([
            'matter_number' => app(GenerateMatterNumber::class)->handle($matterType),
            'client_id' => $client->id,
            'matter_type_id' => $matterType->id,
            'responsible_user_id' => $attorney->id,
            'opened_by_user_id' => $attorney->id,
            'title' => $record['matter']['title'],
            'description' => $record['matter']['description'],
            'status' => 'open',
            'priority' => $record['matter']['priority'],
            'billing_type' => $billingType,
            'default_hourly_rate' => $billingType === 'hourly' ? $matterType->default_hourly_rate : null,
            'budget_amount' => $record['matter']['budget_amount'],
            'opened_at' => now()->toDateString(),
            'deadline_at' => now()->addDays($record['matter']['deadline_in_days'])->toDateString(),
            'metadata' => ['seed_source' => 'practice_demo_2026'],
        ]);
        $matter->attorneys()->syncWithoutDetaching([$attorney->id]);

        return $matter;
    }

    /**
     * @param  array{type: string, name: string, email: string, phone: string, city: string}  $attributes
     */
    private function seedClient(array $attributes): Client
    {
        $normalizedEmail = app(ClientContactManager::class)->normalizeEmail($attributes['email']);
        $existingClient = Client::query()
            ->whereHas('emailContact', fn ($contact) => $contact->where('normalized_value', $normalizedEmail))
            ->first();

        if ($existingClient !== null) {
            return $existingClient;
        }

        return Client::create([
            'client_number' => app(GenerateClientNumber::class)->handle(),
            'type' => $attributes['type'],
            'full_name' => $attributes['type'] === 'individual' ? $attributes['name'] : null,
            'organization_name' => $attributes['type'] === 'organization' ? $attributes['name'] : null,
            'contact_person' => $attributes['type'] === 'organization' ? 'Practice demo contact' : null,
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
            'address_line1' => 'Practice demo address',
            'city' => $attributes['city'],
            'country' => 'Malawi',
            'status' => 'active',
            'metadata' => ['seed_source' => 'practice_demo_2026'],
        ]);
    }

    /**
     * @param  array{record_index: int, title: string, description: string, type: string, location: string, starts_in_days: int, hour: int}  $attributes
     */
    private function seedEvent(Matter $matter, array $attributes): void
    {
        if (Appointment::query()->where('matter_id', $matter->id)->where('title', $attributes['title'])->exists()) {
            return;
        }

        $startsAt = Carbon::now()->addDays($attributes['starts_in_days'])->setTime($attributes['hour'], 0);

        Appointment::create([
            'matter_id' => $matter->id,
            'client_id' => $matter->client_id,
            'organizer_user_id' => $matter->responsible_user_id,
            'title' => $attributes['title'],
            'description' => $attributes['description'],
            'type' => $attributes['type'],
            'status' => 'scheduled',
            'location' => $attributes['location'],
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'is_all_day' => false,
        ]);
    }
}
