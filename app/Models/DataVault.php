<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/** Encrypted staff data (table staff_data_vault). Only the attributes the report card app needs. */
class DataVault extends Model
{
    use HasUuids, ReadOnlyModel;

    protected $table = 'staff_data_vault';

    protected $hidden = ['nip_encrypted', 'email_encrypted'];

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => ! empty($attributes['email_encrypted']) ? Crypt::decryptString($attributes['email_encrypted']) : null,
        );
    }

    protected function nip(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => ! empty($attributes['nip_encrypted']) ? Crypt::decryptString($attributes['nip_encrypted']) : null,
        );
    }
}
