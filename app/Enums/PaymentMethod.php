<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'online';
    case CashOnArrival = 'cash_on_arrival';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Invoice = 'invoice';

    /** Methods a customer can choose on the website. */
    public function isCustomerFacing(): bool
    {
        return in_array($this, [self::Online, self::CashOnArrival], true);
    }
}
