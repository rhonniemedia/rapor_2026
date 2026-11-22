<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Eloquent builder that refuses every write. Used by read-only models of the shared database.
 * Signatures use variadics and the `never` return type so they stay compatible with the parent builder.
 */
class ReadOnlyBuilder extends Builder
{
    private function refuse(): never
    {
        throw new LogicException('The shared database is read-only for the report card app (' . get_class($this->getModel()) . ').');
    }

    public function update(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function upsert(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function increment(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function decrement(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function delete(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function forceDelete(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function insert(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function insertGetId(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function insertOrIgnore(mixed ...$arguments): never
    {
        $this->refuse();
    }

    public function truncate(mixed ...$arguments): never
    {
        $this->refuse();
    }
}
