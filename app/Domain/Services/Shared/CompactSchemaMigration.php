<?php

namespace VistiqCore\Services\Shared;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use VistiqAdmin\Services\Admin\System\DatabaseBackupService;

class CompactSchemaMigration
{
    public function up(): void
    {
        config(['compact-schema' => require config_path('compact-schema.php')]);
        if (DB::getDriverName() === 'mysql' && ! app()->environment('testing') && ! app()->isDownForMaintenance()) {
            throw new RuntimeException('Run php artisan vistiq:compact-database; it enables maintenance and creates a verified backup before changing data.');
        }
        if (config('cache.default') === 'database') {
            throw new RuntimeException('Set CACHE_STORE=file and run php artisan config:clear before consolidating the database.');
        }
        if (Schema::hasTable('job_batches') && DB::table('job_batches')->where('pending_jobs', '>', 0)->whereNull('cancelled_at')->exists()) {
            throw new RuntimeException('Finish or cancel outstanding job batches before consolidation.');
        }
        $definitions = config('compact-schema');
        $state = DB::table('system_settings')->where('group', 'compact.migration')->value('value');
        $state = $state ? CompactDatabase::rows($state) : null;
        if (! $state) {
            $state = ['driver' => DB::getDriverName(), 'tables' => [], 'foreign_keys' => [], 'backup' => null, 'definitions' => $definitions];
            if (! app()->environment('testing')) {
                $directory = storage_path('app/private/database-consolidation');
                if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
                    throw new RuntimeException('Cannot create the private database backup directory.');
                }
                $state['backup'] = $directory.'/before-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
                $stream = fopen($state['backup'], 'xb');
                try {
                    app(DatabaseBackupService::class)->export(true, true, $stream);
                    fflush($stream);
                } finally {
                    fclose($stream);
                }
                $state['backup_sha256'] = hash_file('sha256', $state['backup']);
                if (filesize($state['backup']) === 0 || ! $state['backup_sha256']) {
                    throw new RuntimeException('Database backup is incomplete. No tables were changed.');
                }
            }
            foreach ($definitions as $name => $spec) {
                if (! Schema::hasTable($name)) {
                    continue;
                }
                $sql = DB::getDriverName() === 'mysql'
                    ? (array) DB::selectOne('SHOW CREATE TABLE `'.$name.'`')
                    : ['sql' => DB::table('sqlite_master')->where('type', 'table')->where('name', $name)->value('sql')];
                $state['tables'][$name] = ['sql' => end($sql), 'count' => DB::table($name)->count(), 'hash' => $this->digest(DB::table($name), $spec)];
                if (DB::getDriverName() === 'sqlite') {
                    $state['tables'][$name]['indexes'] = DB::table('sqlite_master')->where('type', 'index')->where('tbl_name', $name)->whereNotNull('sql')->pluck('sql')->all();
                }
            }
            $schema = DB::getDriverName() === 'mysql' ? DB::getDatabaseName() : null;
            foreach (Schema::getTableListing(schema: $schema, schemaQualified: false) as $table) {
                if (isset($definitions[$table])) {
                    continue;
                }
                if (DB::getDriverName() === 'mysql' && in_array($table, ['learning_items', 'learning_item_meanings'], true)) {
                    foreach (Schema::getIndexes($table) as $index) {
                        if (strtolower($index['type']) === 'fulltext') {
                            $state['fulltext_indexes'][$table][] = $index;
                        }
                    }
                }
                foreach (Schema::getForeignKeys($table) as $foreign) {
                    if (isset($definitions[$foreign['foreign_table']])) {
                        $state['foreign_keys'][$table][] = $foreign;
                    }
                }
            }
            $this->saveState($state);
        }
        if (DB::getDriverName() === 'mysql' && ! array_key_exists('fulltext_indexes', $state)) {
            $state['fulltext_indexes'] = [];
            foreach (['learning_items', 'learning_item_meanings'] as $table) {
                foreach (Schema::getIndexes($table) as $index) {
                    if (strtolower($index['type']) === 'fulltext') {
                        $state['fulltext_indexes'][$table][] = $index;
                    }
                }
            }
            $this->saveState($state);
        }
        // Rebuild search indexes once after bulk backfills instead of rewriting them for every row.
        foreach ($state['fulltext_indexes'] ?? [] as $table => $indexes) {
            $existing = array_column(Schema::getIndexes($table), 'name');
            foreach ($indexes as $index) {
                if (in_array($index['name'], $existing, true)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropFullText($index['name']));
                }
            }
        }
        Schema::table('system_settings', fn (Blueprint $table) => $table->string('group', 100)->change());
        if (! Schema::hasColumn('audit_logs', 'source_activity_id')) {
            Schema::table('audit_logs', fn (Blueprint $table) => $table->unsignedBigInteger('source_activity_id')->nullable()->unique());
        }
        $classifications = [
            ['learning_items', 'learning_item_type_id', 'learning_item_types', 'type', 'code'],
            ['learning_items', 'learning_item_type_id', 'learning_item_types', 'type_label', 'label'],
            ['learning_items', 'proficiency_level_id', 'proficiency_levels', 'proficiency_level', 'code'],
            ['learning_item_meanings', 'part_of_speech_id', 'parts_of_speech', 'part_of_speech', 'code'],
            ['grammar_topics', 'grammar_category_id', 'grammar_categories', 'category', 'slug'],
            ['exam_parts', 'skill_id', 'skills', 'skill_code', 'code'],
            ['exam_templates', 'practice_mode_id', 'practice_modes', 'practice_mode', 'code'],
            ['question_bank_items', 'question_difficulty_id', 'question_difficulties', 'difficulty_code', 'code'],
            ['attempt_events', 'attempt_event_type_id', 'attempt_event_types', 'event_type', 'code'],
            ['attempt_reports', 'attempt_report_reason_id', 'attempt_report_reasons', 'reason_code', 'code'],
        ];
        $additions = [];
        foreach ($definitions as $spec) {
            if (isset($spec['relation'])) {
                continue;
            }
            if (isset($spec['fields'])) {
                foreach ($spec['fields'] as $old => $new) {
                    if (! Schema::hasColumn($spec['parent'], $new)) {
                        $additions[$spec['parent']][$new] = $spec['columns'][$old];
                    }
                }
            } elseif (! in_array($spec['parent'], ['system_settings', 'audit_logs'], true) && ! Schema::hasColumn($spec['parent'], $spec['column'])) {
                $additions[$spec['parent']][$spec['column']] = 'json';
            }
        }
        foreach ($classifications as [$parent, $id, $catalog, $field, $value]) {
            if (! Schema::hasColumn($parent, $field)) {
                $additions[$parent][$field] = 'classification';
            }
        }
        // One ALTER per owner avoids repeatedly rebuilding MySQL tables with full-text indexes.
        foreach ($additions as $parent => $columns) {
            if (DB::getDriverName() === 'mysql') {
                $clauses = [];
                foreach ($columns as $name => $type) {
                    $sqlType = match (true) {
                        $type === 'classification' => 'VARCHAR(140)',
                        in_array($type, ['bigint', 'int', 'smallint', 'tinyint']) => 'BIGINT',
                        $type === 'decimal' => 'DECIMAL(12,2)',
                        $type === 'json' => 'JSON',
                        $type === 'timestamp' => 'TIMESTAMP',
                        default => 'TEXT',
                    };
                    $clauses[] = "ADD `$name` $sqlType NULL";
                    if ($type === 'classification') {
                        $clauses[] = "ADD INDEX `{$parent}_{$name}_index` (`$name`)";
                    }
                }
                DB::statement("ALTER TABLE `$parent` ".implode(', ', $clauses));

                continue;
            }
            Schema::table($parent, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $name => $type) {
                    match (true) {
                        $type === 'classification' => $table->string($name, 140)->nullable()->index(),
                        in_array($type, ['bigint', 'int', 'smallint', 'tinyint']) => $table->bigInteger($name)->nullable(),
                        $type === 'decimal' => $table->decimal($name, 12, 2)->nullable(),
                        $type === 'json' => $table->json($name)->nullable(),
                        $type === 'timestamp' => $table->timestamp($name)->nullable(),
                        default => $table->text($name)->nullable(),
                    };
                }
            });
        }
        // Copy every source before removing any of them, including sources used by another mapping.
        foreach ($definitions as $name => $spec) {
            if (! Schema::hasTable($name)) {
                continue;
            }
            if (array_key_exists('id', $spec['columns'])) {
                DB::table('system_settings')->updateOrInsert(['group' => 'compact.sequence.'.$name], ['value' => (string) (DB::table($name)->max('id') ?? 0)]);
            }
            $expected = $state['tables'][$name] ?? null;
            $projection = DB::table(CompactDatabase::source($name, true));
            if ($expected && $projection->count() === $expected['count'] && $this->digest($projection, $spec) === $expected['hash']) {
                continue;
            }
            $this->copy($name, $spec);
        }
        foreach ($state['tables'] as $name => $expected) {
            $actual = DB::table(CompactDatabase::source($name, true));
            if ($actual->count() !== $expected['count'] || $this->digest($actual, $definitions[$name]) !== $expected['hash']) {
                throw new RuntimeException("Verification failed for $name. All remaining source tables and the backup have been retained.");
            }
        }
        $classificationUpdates = [];
        foreach ($classifications as [$parent, $id, $catalog, $field, $value]) {
            if (! Schema::hasColumn($parent, $field)) {
                Schema::table($parent, fn (Blueprint $table) => $table->string($field, 140)->nullable()->index());
            }
            $source = CompactDatabase::source($catalog);
            $subquery = DB::table($source)->select($value)->whereColumn($catalog.'.id', $parent.'.'.$id)->limit(1);
            $classificationUpdates[$parent][$field] = $subquery;
        }
        foreach ($classificationUpdates as $parent => $values) {
            DB::table($parent)->update($values);
        }
        if (! Schema::hasColumn('question_bank_items', 'status')) {
            foreach (Schema::getForeignKeys('question_bank_items') as $foreign) {
                if ($foreign['columns'] === ['learning_item_id']) {
                    Schema::table('question_bank_items', fn (Blueprint $table) => $table->dropForeign(DB::getDriverName() === 'sqlite' ? $foreign['columns'] : $foreign['name']));
                }
            }
            Schema::table('question_bank_items', function (Blueprint $table): void {
                $table->string('status', 24)->default('draft')->index();
                $table->timestamp('published_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->index('learning_item_id', 'qbi_learning_item_idx');
                $table->dropUnique(['learning_item_id']);
                $table->unsignedBigInteger('learning_item_id')->nullable()->change();
                $table->foreign('learning_item_id')->references('id')->on('learning_items')->nullOnDelete();
            });
            foreach (['status', 'published_at', 'created_by'] as $field) {
                DB::table('question_bank_items')->whereNotNull('learning_item_id')->update([$field => DB::table('learning_items')->select($field)->whereColumn('learning_items.id', 'question_bank_items.learning_item_id')->limit(1)]);
            }
        }
        foreach ($state['fulltext_indexes'] ?? [] as $table => $indexes) {
            foreach ($indexes as $index) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->fullText($index['columns'], $index['name']));
            }
        }
        // Only verified copies are eligible for removal. All unrelated foreign keys stay intact.
        foreach ($state['foreign_keys'] as $table => $foreigns) {
            $existing = array_column(Schema::getForeignKeys($table), 'name');
            foreach ($foreigns as $foreign) {
                if (in_array($foreign['name'], $existing, true)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign(DB::getDriverName() === 'sqlite' ? $foreign['columns'] : $foreign['name']));
                }
            }
        }
        Schema::disableForeignKeyConstraints();
        try {
            foreach (array_keys($definitions) as $name) {
                Schema::dropIfExists($name);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $state['verified_at'] = now()->toIso8601String();
        $state['physical_tables'] = count(Schema::getTableListing(schema: DB::getDriverName() === 'mysql' ? DB::getDatabaseName() : null, schemaQualified: false));
        $this->saveState($state);
    }

    private function copy(string $name, array $spec): void
    {
        if (isset($spec['relation'])) {
            foreach (DB::table($name)->orderBy('collocation_id')->orderBy('dictionary_entry_id')->cursor() as $row) {
                CompactDatabase::write($name, (array) $row);
            }

            return;
        }
        if (isset($spec['fields']) && DB::getDriverName() === 'mysql') {
            $assignments = [];
            foreach ($spec['fields'] as $old => $new) {
                if ($old !== $spec['owner']) {
                    $assignments[] = "p.`$new` = s.`$old`";
                }
            }
            DB::statement('UPDATE `'.$spec['parent'].'` p JOIN `'.$name.'` s ON s.`'.$spec['owner'].'` = p.id SET '.implode(', ', $assignments));

            return;
        }
        if ($name === 'user_activities') {
            DB::transaction(function () use ($name): void {
                foreach (DB::table($name)->orderBy('id')->cursor() as $row) {
                    CompactDatabase::write($name, (array) $row);
                }
            });

            return;
        }
        if (DB::getDriverName() === 'mysql') {
            $pairs = [];
            foreach (array_keys($spec['columns']) as $column) {
                $pairs[] = "'$column', s.`$column`";
            }
            $json = 'JSON_OBJECT('.implode(', ', $pairs).')';
            if ($spec['parent'] === 'system_settings') {
                $rows = DB::selectOne("SELECT COALESCE(JSON_ARRAYAGG($json), JSON_ARRAY()) AS payload FROM `$name` s")->payload;
                DB::table('system_settings')->updateOrInsert(['group' => 'compact.'.$name], ['value' => $rows]);

                return;
            }
            $join = $name === 'collocation_dictionary_entries' ? ' JOIN collocations c ON c.id = s.collocation_id' : '';
            $owner = $name === 'collocation_dictionary_entries' ? 'c.learning_item_id' : 's.`'.$spec['owner'].'`';
            $payload = isset($spec['path']) ? 'JSON_SET(COALESCE(p.`'.$spec['column']."`, JSON_OBJECT()), '$.".$spec['path']."', data.payload)" : 'data.payload';
            $extra = '';
            if ($name === 'attempt_answer_options') {
                $join = ' JOIN question_options q ON q.id = s.question_option_id';
                $extra = ', JSON_ARRAYAGG(s.question_option_id) AS option_ids, JSON_ARRAYAGG(q.option_key) AS option_keys';
                $payload = "JSON_SET($payload, '$.option_ids', data.option_ids, '$.options', COALESCE(JSON_EXTRACT(p.response, '$.options'), data.option_keys))";
            }
            DB::statement('UPDATE `'.$spec['parent'].'` p JOIN (SELECT '.$owner.' AS owner_id, JSON_ARRAYAGG('.$json.') AS payload'.$extra.' FROM `'.$name.'` s'.$join.' GROUP BY '.$owner.') data ON data.owner_id = p.id SET p.`'.$spec['column'].'` = '.$payload);

            return;
        }
        if (isset($spec['fields'])) {
            // Flat owners are updated individually below, preserving the owner's unrelated columns.
        } elseif ($spec['parent'] === 'system_settings') {
            DB::table('system_settings')->updateOrInsert(['group' => 'compact.'.$name], ['value' => '[]']);
        } elseif (! isset($spec['path'])) {
            DB::table($spec['parent'])->update([$spec['column'] => null]);
        }
        $query = DB::table($name);
        foreach ($spec['primary'] as $key) {
            $query->orderBy($key);
        }
        foreach ($query->cursor() as $row) {
            CompactDatabase::write($name, (array) $row);
        }
    }

    private function digest(Builder $query, array $spec): string
    {
        $query = clone $query;
        foreach ($spec['primary'] as $key) {
            $query->orderBy($key);
        }
        $hash = hash_init('sha256');
        foreach ($query->cursor() as $row) {
            $values = [];
            foreach ($spec['columns'] as $key => $type) {
                $value = $row->$key;
                if ($value !== null) {
                    $value = match (true) {
                        in_array($type, ['bigint', 'int', 'integer', 'smallint', 'tinyint', 'mediumint']) => (string) (int) $value,
                        in_array($type, ['decimal', 'double', 'float', 'numeric']) => sprintf('%.6f', (float) $value),
                        $type === 'json' => CompactDatabase::json(json_decode($value, true, 512, JSON_THROW_ON_ERROR)),
                        default => (string) $value,
                    };
                }
                $values[$key] = $value;
            }
            hash_update($hash, CompactDatabase::json($values)."\n");
        }

        return hash_final($hash);
    }

    private function saveState(array $state): void
    {
        DB::table('system_settings')->updateOrInsert(['group' => 'compact.migration'], ['value' => CompactDatabase::json($state)]);
    }

    public function down(): void
    {
        // Recover all rows, including records created after consolidation, before deleting embedded copies.
        $state = CompactDatabase::rows(DB::table('system_settings')->where('group', 'compact.migration')->value('value'));
        if (isset($state['definitions'])) {
            config(['compact-schema' => $state['definitions']]);
        }
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($state['tables'] as $name => $saved) {
                DB::unprepared($saved['sql']);
            }
            foreach ($state['tables'] as $name => $saved) {
                $query = DB::table(CompactDatabase::source($name, true));
                foreach (config('compact-schema.'.$name.'.primary') as $key) {
                    $query->orderBy($key);
                }
                DB::transaction(function () use ($query, $name): void {
                    // Read each JSON projection once; OFFSET pagination would repeatedly
                    // expand the entire dictionary for every restored batch.
                    foreach ($query->cursor()->chunk(100) as $rows) {
                        DB::table($name)->insert($rows->map(fn ($row) => (array) $row)->all());
                    }
                });
                foreach ($saved['indexes'] ?? [] as $index) {
                    DB::unprepared($index);
                }
            }
            foreach ($state['foreign_keys'] as $table => $foreigns) {
                foreach ($foreigns as $foreign) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->foreign($foreign['columns'], $foreign['name'])->references($foreign['foreign_columns'])->on($foreign['foreign_table'])->onDelete($foreign['on_delete'])->onUpdate($foreign['on_update']));
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        DB::table('audit_logs')->whereNotNull('source_activity_id')->delete();
        DB::table('system_settings')->where('group', 'like', 'compact.%')->delete();
    }
}
