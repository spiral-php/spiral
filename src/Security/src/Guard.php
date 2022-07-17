<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

declare(strict_types=1);

namespace Spiral\Security;

use Spiral\Security\Exception\GuardException;

/**
 * Checks permissions using given actor.
 */
final class Guard implements GuardInterface
{
    private PermissionsInterface $permissions;

    private ?ActorInterface $actor;

    private array $roles = [];

    /**
     * @param array                $roles Session specific roles.
     */
    public function __construct(
        PermissionsInterface $permissions,
        ActorInterface $actor = null,
        array $roles = []
    ) {
        $this->roles = $roles;
        $this->actor = $actor;
        $this->permissions = $permissions;
    }

    /**
     * {@inheritdoc}
     */
    public function allows(string $permission, array $context = []): bool
    {
        $allows = false;
        foreach ($this->getRoles() as $role) {
            if (!$this->permissions->hasRole($role)) {
                continue;
            }

            $rule = $this->permissions->getRule($role, $permission);

            //Checking our rule
            $allows = $allows || $rule->allows($this->getActor(), $permission, $context);
        }

        return $allows;
    }

    /**
     * Currently active actor/session roles.
     *
     *
     * @throws GuardException
     */
    public function getRoles(): array
    {
        return array_merge($this->roles, $this->getActor()->getRoles());
    }

    /**
     * Create instance of guard with session specific roles (existed roles will be droppped).
     */
    public function withRoles(array $roles): Guard
    {
        $guard = clone $this;
        $guard->roles = $roles;

        return $guard;
    }

    /**
     * {@inheritdoc}
     *
     * @throws GuardException
     */
    public function getActor(): ActorInterface
    {
        if (empty($this->actor)) {
            throw new GuardException('Unable to get Guard Actor, no value set');
        }

        return $this->actor;
    }

    /**
     * {@inheritdoc}
     */
    public function withActor(ActorInterface $actor): GuardInterface
    {
        $guard = clone $this;
        $guard->actor = $actor;

        return $guard;
    }
}
