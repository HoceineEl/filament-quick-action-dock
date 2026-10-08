<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Customers\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Customers\CustomerResource;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;
}
