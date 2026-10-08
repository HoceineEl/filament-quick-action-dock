<?php

declare(strict_types=1);

namespace HoceineEl\QuickActionDock\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;

class RoleUser extends User
{
    /**
     * @var array<string>
     */
    public array $assignedRoles = [];

    /**
     * @param  array<string> | string  $roles
     */
    public function hasRole(array|string $roles): bool
    {
        return array_intersect((array) $roles, $this->assignedRoles) !== [];
    }
}
