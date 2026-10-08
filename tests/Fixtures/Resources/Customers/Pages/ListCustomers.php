<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages;

use Filament\Resources\Pages\ListRecords;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
