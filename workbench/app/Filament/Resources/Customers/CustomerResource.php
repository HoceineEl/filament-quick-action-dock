<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Customers;

use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Customers\Pages\EditCustomer;
use Workbench\App\Filament\Resources\Customers\Pages\ListCustomers;
use Workbench\App\Models\Customer;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->required(), TextInput::make('email')->email()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name'), TextColumn::make('email')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
