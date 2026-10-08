<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages;

use Filament\Resources\Pages\ViewRecord;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;
}
