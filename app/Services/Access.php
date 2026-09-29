<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What the signed-in user may see, derived from their roles and the
 * owner / agent / tenant profile linked to their account.
 *
 * Admins are unrestricted: every *Ids() method returns null for them.
 * Other users get the union of what each of their roles allows.
 */
class Access
{
    private ?array $roles = null;
    private array $cache = [];

    public function __construct(private readonly object $user)
    {
    }

    public static function for(object $user): self
    {
        return new self($user);
    }

    public function roles(): array
    {
        return $this->roles ??= DB::table('roles')
            ->join('role_user', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $this->user->id)
            ->pluck('roles.slug')
            ->all();
    }

    public function is(string ...$roles): bool
    {
        return (bool) array_intersect($roles, $this->roles());
    }

    public function isAdmin(): bool
    {
        return $this->is('admin');
    }

    public function ownerId(): ?int
    {
        return $this->profileId('owner', 'owners');
    }

    public function agentId(): ?int
    {
        return $this->profileId('agent', 'agents');
    }

    public function tenantId(): ?int
    {
        return $this->profileId('tenant', 'tenants');
    }

    /** Units the user owns or manages as an agent (not ones they rent). */
    public function managedUnitIds(): array
    {
        return $this->cache['managed'] ??= DB::table('units')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where(function ($q) {
                $q->whereRaw('1 = 0');
                if ($this->ownerId()) {
                    $q->orWhere('properties.owner_id', $this->ownerId());
                }
                if ($this->agentId()) {
                    $q->orWhere('units.agent_id', $this->agentId())
                        ->orWhere('properties.agent_id', $this->agentId());
                }
            })
            ->pluck('units.id')
            ->all();
    }

    public function unitIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->cache['units'] ??= collect($this->managedUnitIds())
            ->merge($this->tenantId()
                ? DB::table('tenancies')->where('tenant_id', $this->tenantId())->pluck('unit_id')
                : [])
            ->unique()->values()->all();
    }

    public function propertyIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->cache['properties'] ??= DB::table('properties')
            ->where(function ($q) {
                $q->whereIn('id', DB::table('units')->whereIn('id', $this->unitIds())->select('property_id'));
                if ($this->ownerId()) {
                    $q->orWhere('owner_id', $this->ownerId());
                }
                if ($this->agentId()) {
                    $q->orWhere('agent_id', $this->agentId());
                }
            })
            ->pluck('id')
            ->all();
    }

    /** Tenancies on units the user owns or manages, plus their own as a tenant. */
    public function tenancyIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->cache['tenancies'] ??= DB::table('tenancies')
            ->where(function ($q) {
                $q->whereIn('unit_id', $this->managedUnitIds());
                if ($this->tenantId()) {
                    $q->orWhere('tenant_id', $this->tenantId());
                }
            })
            ->pluck('id')
            ->all();
    }

    public function tenantIds(): ?array
    {
        if ($this->isAdmin()) {
            return null;
        }

        return $this->cache['tenants'] ??= DB::table('tenancies')
            ->whereIn('id', $this->tenancyIds())
            ->pluck('tenant_id')
            ->when($this->tenantId(), fn ($ids) => $ids->push($this->tenantId()))
            ->unique()->values()->all();
    }

    /** Restrict $column to $ids; null means unrestricted. */
    public static function limit(Builder $query, string $column, ?array $ids): Builder
    {
        return $ids === null ? $query : $query->whereIn($column, $ids);
    }

    private function profileId(string $role, string $table): ?int
    {
        if (! $this->is($role)) {
            return null;
        }

        if (! array_key_exists("profile.$role", $this->cache)) {
            $this->cache["profile.$role"] = DB::table($table)
                ->where('user_id', $this->user->id)
                ->value('id');
        }

        return $this->cache["profile.$role"];
    }
}
