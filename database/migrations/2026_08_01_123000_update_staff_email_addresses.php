<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateEmailAddresses([
            'allan.mhone@zolomphi.co.mw' => 'amhone@zolomphi.com',
            'beatrice.mbewe@zolomphi.co.mw' => 'bmbewe@zolomphi.com',
            'blessings.nthenda@zolomphi.co.mw' => 'bnthenda@zolomphi.com',
            'brenda.kumbukani@zolomphi.co.mw' => 'bkumbukani@zolomphi.com',
            'chikondi.phiri@zolomphi.co.mw' => 'cphiri@zolomphi.com',
            'esnart.chilima@zolomphi.co.mw' => 'echilima@zolomphi.com',
            'gertrude.manda@zolomphi.co.mw' => 'gmanda@zolomphi.com',
            'gift.kachere@zolomphi.co.mw' => 'gkachere@zolomphi.com',
            'isaac.mkandawire@zolomphi.co.mw' => 'imkandawire@zolomphi.com',
            'kelvin.soko@zolomphi.co.mw' => 'ksoko@zolomphi.com',
            'kondwani.chirwa@zolomphi.co.mw' => 'kchirwa@zolomphi.com',
            'linda.kaponda@zolomphi.co.mw' => 'lkaponda@zolomphi.com',
            'lloyd.kalua@zolomphi.co.mw' => 'lkalua@zolomphi.com',
            'madalitso.mvula@zolomphi.co.mw' => 'mmvula@zolomphi.com',
            'mary.nkhata@zolomphi.co.mw' => 'mnkhata@zolomphi.com',
            'mphatso.banda@zolomphi.co.mw' => 'mbanda@zolomphi.com',
            'natasha.zulu@zolomphi.co.mw' => 'nzulu@zolomphi.com',
            'owen.nyirenda@zolomphi.co.mw' => 'onyirenda@zolomphi.com',
            'patricia.gondwe@zolomphi.co.mw' => 'pgondwe@zolomphi.com',
            'peter.kamwendo@zolomphi.co.mw' => 'pkamwendo@zolomphi.com',
            'ruth.nkhoma@zolomphi.co.mw' => 'rnkhoma@zolomphi.com',
            'tamara.chisale@zolomphi.co.mw' => 'tchisale@zolomphi.com',
            'thandiwe.moyo@zolomphi.co.mw' => 'tmoyo@zolomphi.com',
            'tionge.msowoya@zolomphi.co.mw' => 'tmsowoya@zolomphi.com',
            'yamikani.ngoma@zolomphi.co.mw' => 'yngoma@zolomphi.com',
        ]);
    }

    public function down(): void
    {
        $this->updateEmailAddresses([
            'amhone@zolomphi.com' => 'allan.mhone@zolomphi.co.mw',
            'bmbewe@zolomphi.com' => 'beatrice.mbewe@zolomphi.co.mw',
            'bnthenda@zolomphi.com' => 'blessings.nthenda@zolomphi.co.mw',
            'bkumbukani@zolomphi.com' => 'brenda.kumbukani@zolomphi.co.mw',
            'cphiri@zolomphi.com' => 'chikondi.phiri@zolomphi.co.mw',
            'echilima@zolomphi.com' => 'esnart.chilima@zolomphi.co.mw',
            'gmanda@zolomphi.com' => 'gertrude.manda@zolomphi.co.mw',
            'gkachere@zolomphi.com' => 'gift.kachere@zolomphi.co.mw',
            'imkandawire@zolomphi.com' => 'isaac.mkandawire@zolomphi.co.mw',
            'ksoko@zolomphi.com' => 'kelvin.soko@zolomphi.co.mw',
            'kchirwa@zolomphi.com' => 'kondwani.chirwa@zolomphi.co.mw',
            'lkaponda@zolomphi.com' => 'linda.kaponda@zolomphi.co.mw',
            'lkalua@zolomphi.com' => 'lloyd.kalua@zolomphi.co.mw',
            'mmvula@zolomphi.com' => 'madalitso.mvula@zolomphi.co.mw',
            'mnkhata@zolomphi.com' => 'mary.nkhata@zolomphi.co.mw',
            'mbanda@zolomphi.com' => 'mphatso.banda@zolomphi.co.mw',
            'nzulu@zolomphi.com' => 'natasha.zulu@zolomphi.co.mw',
            'onyirenda@zolomphi.com' => 'owen.nyirenda@zolomphi.co.mw',
            'pgondwe@zolomphi.com' => 'patricia.gondwe@zolomphi.co.mw',
            'pkamwendo@zolomphi.com' => 'peter.kamwendo@zolomphi.co.mw',
            'rnkhoma@zolomphi.com' => 'ruth.nkhoma@zolomphi.co.mw',
            'tchisale@zolomphi.com' => 'tamara.chisale@zolomphi.co.mw',
            'tmoyo@zolomphi.com' => 'thandiwe.moyo@zolomphi.co.mw',
            'tmsowoya@zolomphi.com' => 'tionge.msowoya@zolomphi.co.mw',
            'yngoma@zolomphi.com' => 'yamikani.ngoma@zolomphi.co.mw',
        ]);
    }

    /**
     * @param  array<string, string>  $addresses
     */
    private function updateEmailAddresses(array $addresses): void
    {
        foreach ($addresses as $oldEmail => $newEmail) {
            DB::table('users')
                ->where('email', $oldEmail)
                ->update([
                    'email' => $newEmail,
                    'updated_at' => now(),
                ]);
        }
    }
};
