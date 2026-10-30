<?php

namespace VistiqCore\Services\Shared;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use VistiqCore\Models\LearningItem;

/** Data access contract for rows embedded in their owning record after consolidation. */
class CompactDatabase
{
    public static function __callStatic(string $method, array $arguments): mixed
    {
        return DB::$method(...$arguments);
    }

    public static function definition(string $name): ?array
    {
        return config('compact-schema.'.$name);
    }

    public static function embedded(string $name): bool
    {
        return self::definition($name) !== null && ! Schema::hasTable($name);
    }

    public static function table($table, $as = null): Builder
    {
        if (! is_string($table)) {
            return DB::table($table, $as);
        }
        $name = preg_split('/\s+as\s+/i', $table)[0];
        if (! self::embedded($name)) {
            return DB::table($table, $as);
        }
        $connection = DB::connection();
        $builder = new CompactQuery($connection, $connection->getQueryGrammar(), $connection->getPostProcessor());
        $builder->logicalTable = $name;

        return $builder->from(self::source($table));
    }

    public static function source(string $table, bool $force = false): string|Expression
    {
        [$name, $alias] = array_pad(preg_split('/\s+as\s+/i', $table), 2, null);
        if (! $force && ! self::embedded($name)) {
            return $table;
        }
        $spec = self::definition($name);
        $quote = fn ($s) => '`'.str_replace('`', '``', $s).'`';
        if (isset($spec['relation'])) {
            $c = self::source('collocations as c', $force);
            $d = self::source('dictionary_entries as d', $force);
            $grammar = DB::connection()->getQueryGrammar();
            $sql = 'SELECT c.id AS collocation_id, d.id AS dictionary_entry_id FROM learning_item_relations r JOIN '.$grammar->wrapTable($c).' ON c.learning_item_id = r.source_learning_item_id JOIN '.$grammar->wrapTable($d)." ON d.learning_item_id = r.target_learning_item_id WHERE r.relation_type = 'collocation_member'";

            return new Expression('('.$sql.') AS '.$quote($alias ?? $name));
        }
        if (isset($spec['fields'])) {
            $columns = [];
            foreach ($spec['fields'] as $old => $new) {
                $columns[] = 'p.'.$quote($new).' AS '.$quote($old);
            }
            $sql = 'SELECT '.implode(', ', $columns).' FROM '.$quote($spec['parent']).' p WHERE p.'.$quote($spec['fields']['id']).' IS NOT NULL';

            return new Expression('('.$sql.') AS '.$quote($alias ?? $name));
        }
        $json = 'p.'.$quote($spec['column']);
        if (isset($spec['path'])) {
            $json = "JSON_EXTRACT($json, '$.".$spec['path']."')";
        }
        $where = '';
        if ($spec['parent'] === 'system_settings') {
            $where = " WHERE p.`group` = 'compact.$name'";
        } elseif ($name === 'user_activities') {
            $where = ' WHERE p.`source_activity_id` IS NOT NULL';
            $json = DB::connection()->getDriverName() === 'sqlite' ? "json_array(json($json))" : "JSON_ARRAY($json)";
        }
        $columns = [];
        if (DB::connection()->getDriverName() === 'sqlite') {
            foreach ($spec['columns'] as $column => $type) {
                $extract = $column === $spec['owner'] && $spec['parent'] !== 'audit_logs' ? 'p.id' : "json_extract(j.value, '$.$column')";
                if (in_array($type, ['json'], true)) {
                    $extract = "CASE WHEN json_type(j.value, '$.$column') IN ('object','array') THEN $extract ELSE $extract END";
                }
                $columns[] = "$extract AS ".$quote($column);
            }
            $sql = 'SELECT '.implode(', ', $columns).' FROM '.$quote($spec['parent'])." p, json_each(COALESCE($json, '[]')) j".$where;
        } else {
            $select = [];
            foreach ($spec['columns'] as $column => $type) {
                $sqlType = match (true) {
                    in_array($type, ['bigint', 'int', 'integer', 'smallint', 'tinyint', 'mediumint']) => 'BIGINT',
                    in_array($type, ['decimal', 'double', 'float', 'numeric']) => 'DECIMAL(20,6)',
                    $type === 'json' => 'JSON',
                    in_array($type, ['timestamp', 'datetime'], true) => 'DATETIME',
                    default => 'LONGTEXT',
                };
                $columns[] = $quote($column)." $sqlType PATH '$.$column' NULL ON EMPTY NULL ON ERROR";
                $select[] = $column === $spec['owner'] && $spec['parent'] !== 'audit_logs' ? 'p.id AS '.$quote($column) : 'j.'.$quote($column);
            }
            $sql = 'SELECT '.implode(', ', $select).' FROM '.$quote($spec['parent'])." p, JSON_TABLE(COALESCE($json, JSON_ARRAY()), '$[*]' COLUMNS (".implode(', ', $columns).')) j'.$where;
        }

        return new Expression('('.$sql.') AS '.$quote($alias ?? $name));
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function rows(mixed $value): array
    {
        return $value === null ? [] : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
    }

    /** All JSON writes lock the parent, so independent answer/profile edits cannot overwrite one another. */
    public static function write(string $name, array $row, bool $delete = false): void
    {
        foreach ($row as $key => $value) {
            if ($value instanceof \DateTimeInterface) {
                $row[$key] = $value->format('Y-m-d H:i:s');
            }
        }
        $spec = self::definition($name);
        foreach ($spec['columns'] as $key => $type) {
            if (! array_key_exists($key, $row) || $row[$key] === null) {
                continue;
            }
            if (in_array($type, ['bigint', 'int', 'integer', 'smallint', 'tinyint', 'mediumint'], true)) {
                $row[$key] = (int) $row[$key];
            } elseif ($type === 'json' && ! isset($spec['fields']) && is_string($row[$key])) {
                $row[$key] = json_decode($row[$key], true, 512, JSON_THROW_ON_ERROR);
            }
        }
        DB::transaction(function () use ($name, $row, $delete, $spec): void {
            if (isset($spec['relation'])) {
                $source = self::table('collocations')->where('id', $row['collocation_id'])->value('learning_item_id');
                $target = self::table('dictionary_entries')->where('id', $row['dictionary_entry_id'])->value('learning_item_id');
                if (! $source || ! $target) {
                    throw new RuntimeException('Invalid collocation/dictionary relationship.');
                }
                $key = ['source_learning_item_id' => $source, 'target_learning_item_id' => $target, 'relation_type' => 'collocation_member'];
                if ($delete) {
                    DB::table('learning_item_relations')->where($key)->delete();
                } else {
                    DB::table('learning_item_relations')->updateOrInsert($key, ['created_at' => now(), 'updated_at' => now()]);
                }

                return;
            }
            if (isset($spec['fields'])) {
                $values = [];
                foreach ($spec['fields'] as $old => $new) {
                    if ($old !== $spec['owner']) {
                        $values[$new] = $delete ? null : ($row[$old] ?? null);
                    }
                }
                $owner = DB::table($spec['parent'])->where('id', $row[$spec['owner']]);
                if (! $owner->lockForUpdate()->exists()) {
                    throw new RuntimeException("Missing owner while storing $name.");
                }
                $owner->update($values);

                return;
            }
            if ($name === 'user_activities') {
                $query = DB::table('audit_logs')->where('source_activity_id', $row['id']);
                if ($delete) {
                    $query->delete();
                } elseif ($query->exists()) {
                    $query->update(['after' => self::json($row)]);
                } else {
                    DB::table('audit_logs')->insert(['source_activity_id' => $row['id'], 'actor_id' => $row['user_id'], 'action' => $row['action'], 'entity_type' => 'activity', 'entity_id' => is_numeric($row['entity'] ?? null) ? $row['entity'] : null, 'after' => self::json($row), 'created_at' => $row['occurred_at']]);
                }

                return;
            }
            if ($spec['parent'] === 'system_settings') {
                DB::table('system_settings')->insertOrIgnore(['group' => 'compact.'.$name, 'value' => '[]']);
                $query = DB::table('system_settings')->where('group', 'compact.'.$name);
            } else {
                $owner = $row[$spec['owner']] ?? null;
                if ($name === 'collocation_dictionary_entries') {
                    $owner = self::table('collocations')->where('id', $owner)->value('learning_item_id');
                }
                $query = DB::table($spec['parent'])->where('id', $owner);
            }
            $parent = $query->lockForUpdate()->first();
            if (! $parent) {
                throw new RuntimeException("Missing owner while storing $name; no data was discarded.");
            }
            $container = self::rows($parent->{$spec['column']});
            $rows = isset($spec['path']) ? ($container[$spec['path']] ?? []) : $container;
            $matched = false;
            foreach ($rows as $index => $existing) {
                $same = true;
                foreach ($spec['primary'] as $key) {
                    $same = $same && (string) ($existing[$key] ?? '') === (string) ($row[$key] ?? '');
                }
                if ($same) {
                    if ($delete) {
                        unset($rows[$index]);
                    } else {
                        $rows[$index] = $row;
                    }
                    $matched = true;
                    break;
                }
            }
            if (! $matched && ! $delete) {
                $rows[] = $row;
            }
            $rows = array_values($rows);
            if (isset($spec['path'])) {
                $container[$spec['path']] = array_values($rows);
                if ($name === 'attempt_answer_options') {
                    $container['option_ids'] = array_column($rows, 'question_option_id');
                    $container['options'] ??= DB::table('question_options')->whereIn('id', $container['option_ids'])->orderBy('sort_order')->pluck('option_key')->all();
                }
                $rows = $container;
            }
            $query->update([$spec['column'] => self::json($rows)]);
        });
    }

    public static function nextId(string $name): int
    {
        return DB::transaction(function () use ($name): int {
            $key = 'compact.sequence.'.$name;
            DB::table('system_settings')->insertOrIgnore(['group' => $key, 'value' => '0']);
            $query = DB::table('system_settings')->where('group', $key);
            $value = (int) $query->lockForUpdate()->value('value') + 1;
            $query->update(['value' => (string) $value]);

            return $value;
        });
    }

    public static function reserveId(string $name, int $id): void
    {
        DB::transaction(function () use ($name, $id): void {
            $key = 'compact.sequence.'.$name;
            DB::table('system_settings')->insertOrIgnore(['group' => $key, 'value' => '0']);
            $query = DB::table('system_settings')->where('group', $key);
            $current = (int) $query->lockForUpdate()->value('value');
            if ($id > $current) {
                $query->update(['value' => (string) $id]);
            }
        });
    }

    public static function fillClassifications(): void
    {
        $mappings = LearningItem::CLASSIFICATIONS;
        $mappings['learning_item_meanings'] = ['part_of_speech_id' => ['parts_of_speech', 'part_of_speech', 'code']];
        $mappings['grammar_topics'] = ['grammar_category_id' => ['grammar_categories', 'category', 'slug']];
        foreach ($mappings as $parent => $fields) {
            foreach ($fields as $id => [$catalog, $field, $value]) {
                if (! Schema::hasColumn($parent, $field)) {
                    continue;
                }
                $subquery = self::table($catalog)->select($value)->whereColumn($catalog.'.id', $parent.'.'.$id)->limit(1);
                DB::table($parent)->whereNull($field)->whereNotNull($id)->update([$field => $subquery]);
            }
        }
        if (Schema::hasColumn('learning_items', 'type_label')) {
            $labels = self::table('learning_item_types')->select('label')->whereColumn('learning_item_types.id', 'learning_items.learning_item_type_id')->limit(1);
            DB::table('learning_items')->whereNull('type_label')->update(['type_label' => $labels]);
        }
    }
}
