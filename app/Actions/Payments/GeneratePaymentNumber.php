<?php

namespace App\Actions\Payments;

use App\Models\Payment;

class GeneratePaymentNumber
{
    public function handle(): string
    {
        $number = Payment::query()->lockForUpdate()->latest('id')->value('payment_number');

        return sprintf('PAY-%06d', $number === null ? 1 : ((int) substr($number, 4)) + 1);
    }
}
