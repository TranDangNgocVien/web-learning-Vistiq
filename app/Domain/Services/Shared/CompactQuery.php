<?php

namespace VistiqCore\Services\Shared;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Queryable projection of embedded rows; mutations persist under the owning row's lock. */
class CompactQuery extends Builder
{
    public string $logicalTable;

    public function insert(array $values)
    {
        if ($values === []) {
            return true;
        }
        foreach (array_is_list($values) ? $values : [$values] as $row) {
            $this->insertGetId($row);
        }

        return true;
    }

    public function insertGetId(array $values, $sequence = null)
    {
        $columns = CompactDatabase::definition($this->logicalTable)['columns'];
        if (array_diff(array_keys($values), array_keys($columns))) {
            throw new RuntimeException('Unknown embedded column in '.$this->logicalTable);
        }
        $row = array_fill_keys(array_keys($columns), null);
        foreach (CompactDatabase::definition($this->logicalTable)['defaults'] ?? [] as $column => $default) {
            if (array_key_exists($column, $columns)) {
                $row[$column] = $default;
            }
        }
        $row = array_replace($row, $values);
        if (array_key_exists('id', $columns)) {
            $row['id'] ??= CompactDatabase::nextId($this->logicalTable);
            if (in_array($columns['id'], ['bigint', 'int', 'smallint'], true)) {
                CompactDatabase::reserveId($this->logicalTable, (int) $row['id']);
            }
        }
        foreach ($row as $key => $value) {
            if ($value instanceof Expression) {
                throw new RuntimeException('SQL expressions are not accepted in embedded writes.');
            }
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $primary = CompactDatabase::definition($this->logicalTable)['primary'];
        $query = CompactDatabase::table($this->logicalTable);
        foreach ($primary as $key) {
            $query->where($key, $row[$key]);
        }
        if ($query->exists()) {
            throw new RuntimeException('Duplicate embedded key in '.$this->logicalTable);
        }
        CompactDatabase::write($this->logicalTable, $row);

        return $row['id'] ?? 0;
    }

    public function insertOrIgnore(array $values)
    {
        $count = 0;
        foreach (array_is_list($values) ? $values : [$values] as $row) {
            $query = CompactDatabase::table($this->logicalTable);
            $unique = isset($row['code']) ? ['code'] : (isset($row['slug']) ? ['slug'] : CompactDatabase::definition($this->logicalTable)['primary']);
            foreach ($unique as $key) {
                $query->where($key, $row[$key] ?? null);
            }
            if (! $query->exists()) {
                $this->insertGetId($row);
                $count++;
            }
        }

        return $count;
    }

    public function updateOrInsert(array $attributes, array|callable $values = [])
    {
        return DB::transaction(function () use ($attributes, $values) {
            $query = clone $this;
            $query->where($attributes);
            $exists = $query->exists();
            $values = is_callable($values) ? $values($exists) : $values;
            if ($exists) {
                return (bool) $query->update($values);
            }

            return $this->insert(array_merge($attributes, $values));
        });
    }

    public function update(array $values)
    {
        return DB::transaction(function () use ($values) {
            $rows = $this->get();
            foreach ($rows as $row) {
                CompactDatabase::write($this->logicalTable, array_replace((array) $row, $values));
            }

            return $rows->count();
        });
    }

    public function delete($id = null)
    {
        if ($id !== null) {
            $this->where('id', $id);
        }

        return DB::transaction(function () {
            $rows = $this->get();
            foreach ($rows as $row) {
                CompactDatabase::write($this->logicalTable, (array) $row, true);
            }

            return $rows->count();
        });
    }
}
