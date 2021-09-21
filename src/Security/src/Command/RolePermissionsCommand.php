<?php

declare(strict_types=1);

namespace Spiral\Security\Command;

use Spiral\Console\Command;
use Spiral\Console\Exception\CommandException;
use Spiral\Security\PermissionsInterface;
use Spiral\Security\Rule\AllowRule;
use Symfony\Component\Console\Input\InputArgument;

class RolePermissionsCommand extends Command
{
    protected const NAME = 'security:role:permissions';
    protected const DESCRIPTION = 'Get Role(s) Permissions';

    protected const ARGUMENTS = [
        ['role', InputArgument::OPTIONAL, 'Role to get permissions'],
    ];

    private const TABLE_HEADERS = ['role', 'permission', 'rule', 'allowed'];

    /**
     * @param PermissionsInterface $rbac
     *
     * @throws CommandException
     */
    protected function perform(PermissionsInterface $rbac): void
    {
        $role = $this->argument('role');

        if ($role !== null && !$rbac->hasRole($role)) {
            throw new CommandException('Unknown role provided');
        }

        if ($role !== null) {
            $rows = $this->getRolePermissions($role, $rbac);
        } else {
            $rows = [];

            foreach ($rbac->getRoles() as $role) {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $rows = array_merge(
                    $this->getRolePermissions($role, $rbac),
                    $rows
                );
            }
        }

        $this->table(self::TABLE_HEADERS, $rows)->render();
    }

    /**
     * Can be used in your command to prepare more complex output
     *
     * @param string $rule
     *
     * @return string|null
     */
    protected function markPermissionAllowed(string $rule): ?string
    {
        return $rule === AllowRule::class ? '+' : null;
    }

    private function getRolePermissions(string $role, PermissionsInterface $rbac): array
    {
        $permissions = [];

        foreach ($rbac->getPermissions($role) as $permission => $rule) {
            $permissions[] = [
                'role' => $role,
                'permission' => $permission,
                'rule' => $rule,
                'allowed' => $this->markPermissionAllowed($rule),
            ];
        }

        return $permissions;
    }
}
