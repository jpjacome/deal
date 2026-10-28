<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Customers are created through bookings, so there is no create action.
 */
class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
