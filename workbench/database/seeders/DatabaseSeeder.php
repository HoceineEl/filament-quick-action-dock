<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Workbench\App\Models\Customer;
use Workbench\App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->create(['name' => 'Ada Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);

        foreach (['Ada Lovelace', 'Grace Hopper', 'Alan Turing'] as $name) {
            Customer::query()->create([
                'name' => $name,
                'email' => str($name)->slug('.')->append('@example.com')->toString(),
            ]);
        }
    }
}
