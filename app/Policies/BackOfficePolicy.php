<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * The access rules for back-office records, which are the same for every
 * resource: everyone signed in can look, managers and admins can change
 * things, only admins can delete.
 *
 * One policy is registered against every back-office model rather than a
 * near-identical class per model. When a model grows rules of its own it can
 * have its own policy; until then this is the whole of it.
 */
class BackOfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->canReadBackOffice();
    }

    public function view(User $user, Model $record): bool
    {
        return $user->role->canReadBackOffice();
    }

    public function create(User $user): bool
    {
        return $user->role->canWriteBackOffice();
    }

    public function update(User $user, Model $record): bool
    {
        return $user->role->canWriteBackOffice();
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->role->canDeleteRecords();
    }

    public function deleteAny(User $user): bool
    {
        return $user->role->canDeleteRecords();
    }

    public function restore(User $user, Model $record): bool
    {
        return $user->role->canWriteBackOffice();
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return $user->role->canDeleteRecords();
    }
}
