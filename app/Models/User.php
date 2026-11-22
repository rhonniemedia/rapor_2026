<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Shared account (table users). Read-only here: the report card app never changes accounts.
 *
 * "Remember me" is disabled on purpose: it would write remember_token to the shared users row
 * (and invalidate the remember cookie of the other apps). The three overrides below make the
 * framework treat the token as permanently empty, so nothing is ever written, not even on logout.
 */
class User extends Authenticatable
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'users';

    protected $hidden = ['password'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Data::class, 'staff_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(UserRole::class, 'user_app_roles', 'user_id', 'role_id')
            ->withPivot('id', 'app_id');
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        // intentionally empty
    }

    public function getRememberTokenName(): string
    {
        return '';
    }
}
