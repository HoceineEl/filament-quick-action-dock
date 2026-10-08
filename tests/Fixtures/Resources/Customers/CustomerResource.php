<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers;

use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use HoceineEl\QuickActionDock\Tests\Fixtures\Customer;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages\EditCustomer;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages\ListCustomers;
use HoceineEl\QuickActionDock\Tests\Fixtures\Resources\Customers\Pages\ViewCustomer;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
