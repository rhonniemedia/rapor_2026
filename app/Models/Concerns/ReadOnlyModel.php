<?php

namespace App\Models\Concerns;

use App\Models\Support\ReadOnlyBuilder;
use Closure;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Makes a model of the SHARED database (PINTAR / SIMKA / core) read-only inside the report card app.
 * Any attempt to create, update, save or delete throws, both on model instances and on query builders.
 * Report card tables (rapor_*) do NOT use this trait.
 *
 * Trait ini dipakai pada Eloquent Model. Method event di bawah adalah magic static
 * (Model::__callStatic), sehingga dideklarasikan via @method agar dikenali Intelephense.
 *
 * @method static void creating(Closure|string $callback)
 * @method static void updating(Closure|string $callback)
 * @method static void saving(Closure|string $callback)
 * @method static void deleting(Closure|string $callback)
 */
trait ReadOnlyModel
{
    public static function bootReadOnlyModel(): void
    {
        $refuse = static function (Model $model): never {
            throw new LogicException(
                'The shared database is read-only for the report card app (' . $model::class . ').'
            );
        };

        static::creating($refuse);
        static::updating($refuse);
        static::saving($refuse);
        static::deleting($refuse);
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    public function newEloquentBuilder($query): ReadOnlyBuilder
    {
        return new ReadOnlyBuilder($query);
    }
}
