<?php

use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Add representative clients and active legal files for a ready-to-use practice workspace.
     */
    public function up(): void
    {
        DB::transaction(function (): void {
            $timestamp = now();
            $matterTypeIds = $this->ensureMatterTypes($timestamp);
            $clientIds = $this->ensureClients($timestamp);
            $responsibleUserIds = $this->responsibleUserIds();
            $nextMatterSequence = $this->nextSequence('matters', 'matter_number');

            foreach ($this->matterScenarios() as $round => $scenarios) {
                foreach ($scenarios as $scenarioIndex => $scenario) {
                    $matterIndex = ($round * count($scenarios)) + $scenarioIndex;
                    $client = $this->clients()[$matterIndex % count($this->clients())];
                    $clientName = $client['type'] === 'organization'
                        ? $client['organization_name']
                        : $client['full_name'];
                    $title = $clientName.' — '.$scenario['title'];
                    $existingMatterId = DB::table('matters')->where('title', $title)->value('id');
                    $attributes = [
                        'client_id' => $clientIds[$client['email']],
                        'matter_type_id' => $matterTypeIds[$scenario['matter_type_code']],
                        'responsible_user_id' => $responsibleUserIds[$scenario['responsible_email']],
                        'opened_by_user_id' => $responsibleUserIds[$scenario['responsible_email']],
                        'title' => $title,
                        'description' => $scenario['description'],
                        'status' => $scenario['status'],
                        'priority' => $scenario['priority'],
                        'billing_type' => $scenario['billing_type'],
                        'default_hourly_rate' => $scenario['hourly_rate'],
                        'budget_amount' => $scenario['budget_amount'],
                        'opened_at' => $timestamp->copy()->subDays(140 - $matterIndex)->toDateString(),
                        'metadata' => json_encode(['source' => 'practice-sample-data']),
                        'updated_at' => $timestamp,
                    ];

                    if ($existingMatterId !== null) {
                        DB::table('matters')->where('id', $existingMatterId)->update($attributes);

                        continue;
                    }

                    $matterNumber = sprintf('MAT-%06d', $nextMatterSequence);

                    while (DB::table('matters')->where('matter_number', $matterNumber)->exists()) {
                        $nextMatterSequence++;
                        $matterNumber = sprintf('MAT-%06d', $nextMatterSequence);
                    }

                    DB::table('matters')->insert([
                        ...$attributes,
                        'matter_number' => $matterNumber,
                        'created_at' => $timestamp,
                    ]);

                    $nextMatterSequence++;
                }
            }
        });
    }

    /**
     * Data is intentionally retained when rolling back schema migrations.
     */
    public function down(): void {}

    /**
     * @return array<string, int>
     */
    private function ensureMatterTypes(CarbonInterface $timestamp): array
    {
        $matterTypeIds = [];

        foreach ([
            ['name' => 'Litigation & Dispute Resolution', 'code' => 'litigation-dispute-resolution', 'description' => 'Court proceedings, arbitration, mediation, and commercial disputes.', 'billing_type' => 'hourly', 'hourly_rate' => 85000, 'sort_order' => 1],
            ['name' => 'Corporate & Commercial', 'code' => 'corporate-commercial', 'description' => 'Corporate governance, contracts, and commercial transactions.', 'billing_type' => 'hourly', 'hourly_rate' => 80000, 'sort_order' => 2],
            ['name' => 'Conveyancing & Property', 'code' => 'conveyancing-property', 'description' => 'Property transfers, leases, title work, and development matters.', 'billing_type' => 'fixed_fee', 'hourly_rate' => 65000, 'sort_order' => 3],
            ['name' => 'Employment & Labour', 'code' => 'employment-labour', 'description' => 'Employment advisory, disciplinary, and labour dispute matters.', 'billing_type' => 'hourly', 'hourly_rate' => 70000, 'sort_order' => 4],
            ['name' => 'Family & Estate', 'code' => 'family-estate', 'description' => 'Estate planning, probate, succession, and family matters.', 'billing_type' => 'fixed_fee', 'hourly_rate' => 60000, 'sort_order' => 5],
            ['name' => 'Intellectual Property', 'code' => 'intellectual-property', 'description' => 'Trade marks, copyright, licensing, and brand protection.', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'sort_order' => 6],
            ['name' => 'Debt Recovery', 'code' => 'debt-recovery', 'description' => 'Demand, collection, restructuring, and debt recovery proceedings.', 'billing_type' => 'hourly', 'hourly_rate' => 65000, 'sort_order' => 7],
            ['name' => 'Regulatory & Compliance', 'code' => 'regulatory-compliance', 'description' => 'Regulatory approvals, investigations, and compliance advisory.', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'sort_order' => 8],
        ] as $matterType) {
            $attributes = [
                'name' => $matterType['name'],
                'description' => $matterType['description'],
                'default_billing_type' => $matterType['billing_type'],
                'default_hourly_rate' => $matterType['hourly_rate'],
                'is_active' => true,
                'sort_order' => $matterType['sort_order'],
                'deleted_at' => null,
                'updated_at' => $timestamp,
            ];
            $matterTypeId = DB::table('matter_types')->where('code', $matterType['code'])->value('id');

            if ($matterTypeId === null) {
                $matterTypeId = DB::table('matter_types')->insertGetId([
                    ...$attributes,
                    'code' => $matterType['code'],
                    'created_at' => $timestamp,
                ]);
            } else {
                DB::table('matter_types')->where('id', $matterTypeId)->update($attributes);
            }

            $matterTypeIds[$matterType['code']] = $matterTypeId;
        }

        return $matterTypeIds;
    }

    /**
     * @return array<string, int>
     */
    private function ensureClients(CarbonInterface $timestamp): array
    {
        $clientIds = [];
        $nextClientSequence = $this->nextSequence('clients', 'client_number');

        foreach ($this->clients() as $client) {
            $clientId = DB::table('clients')->where('email', $client['email'])->value('id');
            $attributes = [
                ...$client,
                'status' => 'active',
                'metadata' => json_encode(['source' => 'practice-sample-data']),
                'updated_at' => $timestamp,
                'deleted_at' => null,
            ];

            if ($clientId === null) {
                $clientNumber = sprintf('CLI-%06d', $nextClientSequence);

                while (DB::table('clients')->where('client_number', $clientNumber)->exists()) {
                    $nextClientSequence++;
                    $clientNumber = sprintf('CLI-%06d', $nextClientSequence);
                }

                $clientId = DB::table('clients')->insertGetId([
                    ...$attributes,
                    'client_number' => $clientNumber,
                    'created_at' => $timestamp,
                ]);
                $nextClientSequence++;
            } else {
                DB::table('clients')->where('id', $clientId)->update($attributes);
            }

            $clientIds[$client['email']] = $clientId;
        }

        return $clientIds;
    }

    /**
     * @return array<string, int>
     */
    private function responsibleUserIds(): array
    {
        $emails = [
            'kchirwa@zolomphi.com',
            'rnkhoma@zolomphi.com',
            'tmsowoya@zolomphi.com',
            'gkachere@zolomphi.com',
            'pkamwendo@zolomphi.com',
            'echilima@zolomphi.com',
            'bnthenda@zolomphi.com',
            'bmbewe@zolomphi.com',
            'mmvula@zolomphi.com',
            'nzulu@zolomphi.com',
        ];
        $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id', 'email')->all();

        if (count($userIds) !== count($emails)) {
            throw new RuntimeException('Practice sample matters require the configured non-ICT legal staff.');
        }

        return $userIds;
    }

    private function nextSequence(string $table, string $column): int
    {
        $latestNumber = DB::table($table)->orderByDesc('id')->value($column);
        $lastSequence = (int) Str::afterLast($latestNumber ?? '', '-');

        return max($lastSequence + 1, 1);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clients(): array
    {
        return [
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Tiwale Manufacturing Limited', 'contact_person' => 'Madalitso Tiwale', 'email' => 'legal@tiwale.client.zolomphi.com', 'phone' => '+265991200001', 'alternate_phone' => null, 'tax_number' => 'MRA-TIW-101', 'registration_number' => 'C-101/2014', 'address_line1' => 'Kanengo Industrial Area', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Manufacturing and supply-chain client.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Chisomo Holdings Plc', 'contact_person' => 'Annie Chisomo', 'email' => 'legal@chisomo.client.zolomphi.com', 'phone' => '+265991200002', 'alternate_phone' => null, 'tax_number' => 'MRA-CHI-102', 'registration_number' => 'C-102/2008', 'address_line1' => 'City Centre', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Diversified investment group.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Lakeview Agro Processors Limited', 'contact_person' => 'Davis Mbewe', 'email' => 'legal@lakeviewagro.client.zolomphi.com', 'phone' => '+265991200003', 'alternate_phone' => null, 'tax_number' => 'MRA-LAP-103', 'registration_number' => 'C-103/2016', 'address_line1' => 'Mchinji Road', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Agricultural processing business.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Nkhalango Estates Limited', 'contact_person' => 'Lameck Phiri', 'email' => 'legal@nkhalango.client.zolomphi.com', 'phone' => '+265991200004', 'alternate_phone' => null, 'tax_number' => 'MRA-NKE-104', 'registration_number' => 'C-104/2012', 'address_line1' => 'Area 43', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Property development and estates client.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Moyo Health Services Limited', 'contact_person' => 'Dr. Thokozani Moyo', 'email' => 'legal@moyohealth.client.zolomphi.com', 'phone' => '+265991200005', 'alternate_phone' => null, 'tax_number' => 'MRA-MHS-105', 'registration_number' => 'C-105/2019', 'address_line1' => 'Area 12', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private healthcare provider.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Mphamvu Energy Malawi Limited', 'contact_person' => 'Tapiwa Mbewe', 'email' => 'legal@mphamvuenergy.client.zolomphi.com', 'phone' => '+265991200006', 'alternate_phone' => null, 'tax_number' => 'MRA-MEM-106', 'registration_number' => 'C-106/2015', 'address_line1' => 'Area 3', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Renewable energy developer.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Nyasa Logistics Limited', 'contact_person' => 'Roderick Manda', 'email' => 'legal@nyasalogistics.client.zolomphi.com', 'phone' => '+265991200007', 'alternate_phone' => null, 'tax_number' => 'MRA-NYL-107', 'registration_number' => 'C-107/2011', 'address_line1' => 'Maselema Road', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Regional freight and logistics provider.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Mzuzu Foods Limited', 'contact_person' => 'Chisomo Nkhoma', 'email' => 'legal@mzuzufoods.client.zolomphi.com', 'phone' => '+265991200008', 'alternate_phone' => null, 'tax_number' => 'MRA-MZF-108', 'registration_number' => 'C-108/2017', 'address_line1' => 'Chibavi', 'address_line2' => null, 'city' => 'Mzuzu', 'state' => 'Northern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Food production and distribution company.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Kachere Construction Limited', 'contact_person' => 'George Kachere', 'email' => 'legal@kachereconstruction.client.zolomphi.com', 'phone' => '+265991200009', 'alternate_phone' => null, 'tax_number' => 'MRA-KCL-109', 'registration_number' => 'C-109/2010', 'address_line1' => 'Chichiri', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Construction and infrastructure contractor.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Golden Harvest Exporters Limited', 'contact_person' => 'Ellen Gondwe', 'email' => 'legal@goldenharvest.client.zolomphi.com', 'phone' => '+265991200010', 'alternate_phone' => null, 'tax_number' => 'MRA-GHE-110', 'registration_number' => 'C-110/2013', 'address_line1' => 'Limbe', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Agricultural export business.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Utale Bank Limited', 'contact_person' => 'Agnes Chiumia', 'email' => 'legal@utalebank.client.zolomphi.com', 'phone' => '+265991200011', 'alternate_phone' => null, 'tax_number' => 'MRA-UBL-111', 'registration_number' => 'C-111/2007', 'address_line1' => 'Victoria Avenue', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Licensed financial services institution.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Malawi Transit Solutions Limited', 'contact_person' => 'Blessings Phiri', 'email' => 'legal@malawitransit.client.zolomphi.com', 'phone' => '+265991200012', 'alternate_phone' => null, 'tax_number' => 'MRA-MTS-112', 'registration_number' => 'C-112/2018', 'address_line1' => 'Area 18', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Public transport and fleet operator.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Zomba Manufacturing Cooperative', 'contact_person' => 'Memory Zulu', 'email' => 'legal@zombamfg.client.zolomphi.com', 'phone' => '+265991200013', 'alternate_phone' => null, 'tax_number' => 'MRA-ZMC-113', 'registration_number' => 'COOP-113/2016', 'address_line1' => 'Matawale', 'address_line2' => null, 'city' => 'Zomba', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Member-owned light manufacturing cooperative.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Lilongwe Textiles Limited', 'contact_person' => 'Thoko Mphande', 'email' => 'legal@lilongwetextiles.client.zolomphi.com', 'phone' => '+265991200014', 'alternate_phone' => null, 'tax_number' => 'MRA-LTL-114', 'registration_number' => 'C-114/2009', 'address_line1' => 'Kanengo', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Textile manufacturing company.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Green Horizon Farms Limited', 'contact_person' => 'Peter Kaira', 'email' => 'legal@greenhorizon.client.zolomphi.com', 'phone' => '+265991200015', 'alternate_phone' => null, 'tax_number' => 'MRA-GHF-115', 'registration_number' => 'C-115/2014', 'address_line1' => 'Bunda', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Commercial farming and agribusiness client.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Mtendere Education Trust', 'contact_person' => 'Rabecca Mkandawire', 'email' => 'legal@mtendereeducation.client.zolomphi.com', 'phone' => '+265991200016', 'alternate_phone' => null, 'tax_number' => 'MRA-MET-116', 'registration_number' => 'TRUST-116/2018', 'address_line1' => 'Area 47', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Education-focused charitable trust.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Bwalo Hospitality Group Limited', 'contact_person' => 'Yolanda Moyo', 'email' => 'legal@bwalohospitality.client.zolomphi.com', 'phone' => '+265991200017', 'alternate_phone' => null, 'tax_number' => 'MRA-BHG-117', 'registration_number' => 'C-117/2015', 'address_line1' => 'Chileka Road', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Hotel and restaurant operator.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Mwayi Mining Services Limited', 'contact_person' => 'Nicholas Moyo', 'email' => 'legal@mwayimining.client.zolomphi.com', 'phone' => '+265991200018', 'alternate_phone' => null, 'tax_number' => 'MRA-MMS-118', 'registration_number' => 'C-118/2012', 'address_line1' => 'Chitipa Boma', 'address_line2' => null, 'city' => 'Chitipa', 'state' => 'Northern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Mining support services provider.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Chikondi Pharmacy Limited', 'contact_person' => 'Tapiwa Chikondi', 'email' => 'legal@chikondipharmacy.client.zolomphi.com', 'phone' => '+265991200019', 'alternate_phone' => null, 'tax_number' => 'MRA-CPL-119', 'registration_number' => 'C-119/2020', 'address_line1' => 'Area 25', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Community pharmacy and healthcare retailer.'],
            ['type' => 'organization', 'full_name' => null, 'organization_name' => 'Southern Clinics Network', 'contact_person' => 'Dr. Grace Nkhoma', 'email' => 'legal@southernclinics.client.zolomphi.com', 'phone' => '+265991200020', 'alternate_phone' => null, 'tax_number' => 'MRA-SCN-120', 'registration_number' => 'C-120/2017', 'address_line1' => 'Kanjedza', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Network of private outpatient clinics.'],
            ['type' => 'individual', 'full_name' => 'Aisha Kaponda', 'organization_name' => null, 'contact_person' => null, 'email' => 'aisha.kaponda@clients.zolomphi.com', 'phone' => '+265991200021', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Area 10', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Charles Mbewe', 'organization_name' => null, 'contact_person' => null, 'email' => 'charles.mbewe@clients.zolomphi.com', 'phone' => '+265991200022', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Ndirande', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Dorothy Manda', 'organization_name' => null, 'contact_person' => null, 'email' => 'dorothy.manda@clients.zolomphi.com', 'phone' => '+265991200023', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Chibavi', 'address_line2' => null, 'city' => 'Mzuzu', 'state' => 'Northern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Emmanuel Phiri', 'organization_name' => null, 'contact_person' => null, 'email' => 'emmanuel.phiri@clients.zolomphi.com', 'phone' => '+265991200024', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Matawale', 'address_line2' => null, 'city' => 'Zomba', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Fiona Chirwa', 'organization_name' => null, 'contact_person' => null, 'email' => 'fiona.chirwa@clients.zolomphi.com', 'phone' => '+265991200025', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Area 18', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'George Nkhoma', 'organization_name' => null, 'contact_person' => null, 'email' => 'george.nkhoma@clients.zolomphi.com', 'phone' => '+265991200026', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Maselema', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Hilda Gondwe', 'organization_name' => null, 'contact_person' => null, 'email' => 'hilda.gondwe@clients.zolomphi.com', 'phone' => '+265991200027', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Katoto', 'address_line2' => null, 'city' => 'Mzuzu', 'state' => 'Northern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Isaac Kachere', 'organization_name' => null, 'contact_person' => null, 'email' => 'isaac.kachere@clients.zolomphi.com', 'phone' => '+265991200028', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Area 49', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Juliana Mwale', 'organization_name' => null, 'contact_person' => null, 'email' => 'juliana.mwale@clients.zolomphi.com', 'phone' => '+265991200029', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Chichiri', 'address_line2' => null, 'city' => 'Blantyre', 'state' => 'Southern Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
            ['type' => 'individual', 'full_name' => 'Kelvin Mphande', 'organization_name' => null, 'contact_person' => null, 'email' => 'kelvin.mphande@clients.zolomphi.com', 'phone' => '+265991200030', 'alternate_phone' => null, 'tax_number' => null, 'registration_number' => null, 'address_line1' => 'Area 36', 'address_line2' => null, 'city' => 'Lilongwe', 'state' => 'Central Region', 'postal_code' => null, 'country' => 'Malawi', 'notes' => 'Private client.'],
        ];
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    private function matterScenarios(): array
    {
        $scenarios = [
            ['title' => 'Supplier contract breach and recovery', 'description' => 'Review a supplier default, issue demand notices, and pursue contractual remedies.', 'matter_type_code' => 'litigation-dispute-resolution', 'responsible_email' => 'kchirwa@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 85000, 'budget_amount' => 5500000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Shareholders agreement and governance review', 'description' => 'Prepare a shareholders agreement and refresh governance reserved-matters provisions.', 'matter_type_code' => 'corporate-commercial', 'responsible_email' => 'pkamwendo@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 80000, 'budget_amount' => 3200000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Commercial property acquisition due diligence', 'description' => 'Conduct title due diligence and manage transfer documentation for a commercial property acquisition.', 'matter_type_code' => 'conveyancing-property', 'responsible_email' => 'mmvula@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 65000, 'budget_amount' => 2400000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Senior employee disciplinary process', 'description' => 'Advise on a fair disciplinary process and prepare hearing documentation for a senior employee.', 'matter_type_code' => 'employment-labour', 'responsible_email' => 'rnkhoma@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 70000, 'budget_amount' => 1800000, 'status' => 'pending', 'priority' => 'normal'],
            ['title' => 'Probate application and estate administration', 'description' => 'Obtain probate and administer the estate assets for the beneficiaries.', 'matter_type_code' => 'family-estate', 'responsible_email' => 'nzulu@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 60000, 'budget_amount' => 1500000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Trade mark registration and brand protection', 'description' => 'Clear and file trade mark applications, including brand protection advice.', 'matter_type_code' => 'intellectual-property', 'responsible_email' => 'echilima@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 2100000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Outstanding invoice debt recovery', 'description' => 'Issue demand letters and negotiate recovery of overdue commercial invoices.', 'matter_type_code' => 'debt-recovery', 'responsible_email' => 'gkachere@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 65000, 'budget_amount' => 1200000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Sector licence compliance assessment', 'description' => 'Assess licence obligations and prepare a remediation plan for regulatory compliance.', 'matter_type_code' => 'regulatory-compliance', 'responsible_email' => 'bnthenda@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 2800000, 'status' => 'pending', 'priority' => 'normal'],
            ['title' => 'Employment contract harmonisation programme', 'description' => 'Update employment contracts and workplace policies for a growing workforce.', 'matter_type_code' => 'employment-labour', 'responsible_email' => 'tmsowoya@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 70000, 'budget_amount' => 2400000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Lease renewal and rent review negotiation', 'description' => 'Negotiate lease renewal terms and advise on the commercial rent review.', 'matter_type_code' => 'conveyancing-property', 'responsible_email' => 'nzulu@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 65000, 'budget_amount' => 1700000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Joint venture agreement for regional expansion', 'description' => 'Structure and negotiate a joint venture for expansion into regional markets.', 'matter_type_code' => 'corporate-commercial', 'responsible_email' => 'bmbewe@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 80000, 'budget_amount' => 4200000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Construction payment certificate dispute', 'description' => 'Resolve a dispute over certified works and delayed payment under a construction contract.', 'matter_type_code' => 'litigation-dispute-resolution', 'responsible_email' => 'rnkhoma@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 85000, 'budget_amount' => 4800000, 'status' => 'on_hold', 'priority' => 'high'],
            ['title' => 'Data privacy compliance implementation', 'description' => 'Develop data privacy policies, notices, and operational compliance controls.', 'matter_type_code' => 'regulatory-compliance', 'responsible_email' => 'bnthenda@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 3000000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Trade secret and confidentiality review', 'description' => 'Strengthen confidentiality protections and advise on suspected misuse of trade secrets.', 'matter_type_code' => 'intellectual-property', 'responsible_email' => 'echilima@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 2600000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Loan restructuring and security documentation', 'description' => 'Negotiate restructuring terms and prepare security documentation for existing facilities.', 'matter_type_code' => 'debt-recovery', 'responsible_email' => 'kchirwa@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 65000, 'budget_amount' => 3800000, 'status' => 'pending', 'priority' => 'high'],
            ['title' => 'Land subdivision and title regularisation', 'description' => 'Regularise title documentation and complete approvals for a land subdivision.', 'matter_type_code' => 'conveyancing-property', 'responsible_email' => 'mmvula@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 65000, 'budget_amount' => 3500000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Wrongful dismissal claim defence', 'description' => 'Defend an employer against a wrongful dismissal and unfair labour practice claim.', 'matter_type_code' => 'employment-labour', 'responsible_email' => 'tmsowoya@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 70000, 'budget_amount' => 2900000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Succession planning and will preparation', 'description' => 'Prepare wills, family governance arrangements, and succession planning documents.', 'matter_type_code' => 'family-estate', 'responsible_email' => 'nzulu@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 60000, 'budget_amount' => 1100000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Distribution agreement negotiation', 'description' => 'Negotiate a national distribution agreement and related performance terms.', 'matter_type_code' => 'corporate-commercial', 'responsible_email' => 'pkamwendo@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 80000, 'budget_amount' => 2500000, 'status' => 'pending', 'priority' => 'normal'],
            ['title' => 'Shareholder oppression petition advice', 'description' => 'Advise minority shareholders on remedies arising from alleged oppressive conduct.', 'matter_type_code' => 'litigation-dispute-resolution', 'responsible_email' => 'gkachere@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 85000, 'budget_amount' => 4500000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Environmental impact approval response', 'description' => 'Prepare legal submissions responding to environmental approval conditions.', 'matter_type_code' => 'regulatory-compliance', 'responsible_email' => 'bnthenda@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 3400000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Commercial lease default enforcement', 'description' => 'Advise on enforcing remedies following a tenant default under a commercial lease.', 'matter_type_code' => 'conveyancing-property', 'responsible_email' => 'mmvula@zolomphi.com', 'billing_type' => 'fixed_fee', 'hourly_rate' => 65000, 'budget_amount' => 2200000, 'status' => 'on_hold', 'priority' => 'high'],
            ['title' => 'Software licensing and ownership agreement', 'description' => 'Draft software licensing terms and clarify ownership of developed intellectual property.', 'matter_type_code' => 'intellectual-property', 'responsible_email' => 'echilima@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 75000, 'budget_amount' => 1900000, 'status' => 'open', 'priority' => 'normal'],
            ['title' => 'Debt collection litigation strategy', 'description' => 'Develop a litigation strategy for persistent non-payment by a commercial debtor.', 'matter_type_code' => 'debt-recovery', 'responsible_email' => 'gkachere@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 65000, 'budget_amount' => 3600000, 'status' => 'open', 'priority' => 'high'],
            ['title' => 'Business acquisition legal due diligence', 'description' => 'Complete legal due diligence and transaction documentation for an acquisition.', 'matter_type_code' => 'corporate-commercial', 'responsible_email' => 'bmbewe@zolomphi.com', 'billing_type' => 'hourly', 'hourly_rate' => 80000, 'budget_amount' => 5000000, 'status' => 'open', 'priority' => 'high'],
        ];

        return array_fill(0, 4, $scenarios);
    }
};
