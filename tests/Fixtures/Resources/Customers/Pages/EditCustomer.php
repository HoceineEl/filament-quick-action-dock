<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages;

use Filament\Resources\Pages\EditRecord;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\CustomerResource;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
