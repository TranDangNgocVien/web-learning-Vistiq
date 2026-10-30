<?php

namespace VistiqAdmin\Services\Admin\System;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseBackupService
{
    public function export(bool $includeSchema, bool $includeData, mixed $stream = null): array
    {
        $write = static function (string $text) use ($stream): void {
            if ($stream === null) {
                echo $text;

                return;
            }
            $offset = 0;
            while ($offset < strlen($text)) {
                $written = fwrite($stream, substr($text, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Không thể ghi file sao lưu.');
                }
                $offset += $written;
            }
        };
        $statementCount = 0;
        $writeStatement = static function (string $statement) use ($write, &$statementCount): void {
            $write(rtrim($statement).';'.PHP_EOL);
            $statementCount++;
        };
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Sao lưu hiện hỗ trợ MySQL và SQLite.');
        }

        $tables = $this->tableNames($connection, $driver);

        if ($tables === []) {
            throw new RuntimeException('Không tìm thấy bảng nào trong cơ sở dữ liệu hiện tại. Không tạo file backup rỗng.');
        }

        $write("-- Vistiq Database Backup v2\n");
        $write("-- Driver: {$driver}\n");

        $writeStatement($driver === 'sqlite' ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS=0');

        if ($includeSchema) {
            foreach ($tables as $table) {
                $createStatement = $this->createStatement($connection, $table, $driver);

                if ($createStatement !== null) {
                    $writeStatement($this->makeCreateTableNonDestructive($createStatement));
                }
            }
            if ($driver === 'sqlite') {
                foreach ($connection->select("SELECT sql FROM sqlite_master WHERE type = 'index' AND sql IS NOT NULL ORDER BY name") as $index) {
                    $writeStatement($this->makeCreateIndexNonDestructive($index->sql));
                }
            }
        }

        if ($includeData) {
            $pdo = null;
            $bufferedAttribute = null;
            $previousBuffering = null;
            try {
                if ($driver === 'mysql' && defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
                    $pdo = $connection->getPdo();
                    $bufferedAttribute = \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY;
                    $previousBuffering = $pdo->getAttribute($bufferedAttribute);
                    $pdo->setAttribute($bufferedAttribute, false);
                }

                foreach ($tables as $table) {
                    $columns = $connection->getSchemaBuilder()->getColumnListing($table);

                    if ($columns === []) {
                        continue;
                    }

                    $quotedTable = $this->quoteIdentifier($table, $driver);
                    $writeStatement('DELETE FROM '.$quotedTable);
                    $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->quoteIdentifier($column, $driver), $columns));
                    $insertPrefix = 'INSERT INTO '.$quotedTable.' ('.$quotedColumns.') VALUES ';
                    $query = $connection->table($table)->select($columns);
                    $batch = [];
                    $batchBytes = 0;
                    $flushBatch = static function () use (&$batch, &$batchBytes, $insertPrefix, $writeStatement): void {
                        if ($batch === []) {
                            return;
                        }

                        $writeStatement($insertPrefix.implode(','.PHP_EOL, $batch));
                        $batch = [];
                        $batchBytes = 0;
                    };

                    foreach ($query->cursor() as $row) {
                        $values = array_map(fn (string $column): string => $this->quoteValue($row->{$column}, $connection, $driver), $columns);
                        $tuple = '('.implode(', ', $values).')';
                        $batch[] = $tuple;
                        $batchBytes += strlen($tuple) + 2;

                        if (count($batch) >= 250 || $batchBytes >= 262144) {
                            $flushBatch();
                        }
                    }

                    $flushBatch();
                }
            } finally {
                if ($pdo !== null && $bufferedAttribute !== null && $previousBuffering !== null) {
                    $pdo->setAttribute($bufferedAttribute, $previousBuffering);
                }
            }
        }

        $writeStatement($driver === 'sqlite' ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS=1');

        return ['driver' => $driver, 'statements' => $statementCount, 'tables' => $tables];
    }

    public function restore(string $sql): int
    {
        $this->preflight($sql);
        $stream = fopen('php://temp', 'w+b');
        if (! $stream || ! $this->writeStringToStream($stream, $sql) || rewind($stream) === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Không thể đọc file sao lưu.');
        }

        try {
            return $this->restoreStream($stream);
        } finally {
            fclose($stream);
        }
    }

    public function restoreFile(string $path): int
    {
        $this->preflightFile($path);
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Không thể mở file sao lưu.');
        }

        try {
            return $this->restoreStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function restoreStream($stream): int
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        if (rewind($stream) === false) {
            throw new RuntimeException('Không thể đọc file sao lưu.');
        }
        $statements = $this->statementsFromStream($stream);
        $statementCount = 0;
        $dataTransaction = false;

        try {
            foreach ($statements as $statement) {
                $isData = preg_match('/\A(?:DELETE\s+FROM|INSERT\s+INTO)\b/i', $statement) === 1;
                if ($isData && ! $dataTransaction) {
                    $connection->beginTransaction();
                    $dataTransaction = true;
                } elseif (! $isData && $dataTransaction) {
                    $connection->commit();
                    $dataTransaction = false;
                }
                $connection->unprepared($statement);
                $statementCount++;
            }
            if ($dataTransaction) {
                $connection->commit();
                $dataTransaction = false;
            }
        } finally {
            if ($dataTransaction) {
                $connection->rollBack();
            }
            if ($driver === 'sqlite') {
                $connection->unprepared('PRAGMA foreign_keys = ON');
            } else {
                $connection->unprepared('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        if ($statementCount === 0) {
            throw new RuntimeException('File sao lưu không có câu lệnh SQL để phục hồi.');
        }

        return $statementCount;
    }

    public function preflight(string $sql): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Phục hồi hiện hỗ trợ MySQL và SQLite.');
        }

        if (! preg_match('/\A-- Vistiq Database Backup v(?:1|2)\R-- Driver: (mysql|sqlite)\R/', $sql, $matches)) {
            throw new RuntimeException('File không phải bản sao lưu SQL do Vistiq xuất.');
        }

        if ($matches[1] !== $driver) {
            throw new RuntimeException('Loại cơ sở dữ liệu trong file không khớp với ứng dụng.');
        }

        return $this->inspectStatements($driver, $this->statements($sql));
    }

    public function preflightFile(string $path): array
    {
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Không thể mở file sao lưu.');
        }

        try {
            $header = fgets($stream, 4096);
            $driverHeader = fgets($stream, 4096);
            if (! is_string($header) || ! is_string($driverHeader)
                || ! preg_match('/\A-- Vistiq Database Backup v(?:1|2)\R-- Driver: (mysql|sqlite)\R/', $header.$driverHeader, $matches)) {
                throw new RuntimeException('File không phải bản sao lưu SQL do Vistiq xuất.');
            }

            $connection = DB::connection();
            $driver = $connection->getDriverName();
            if (! in_array($driver, ['mysql', 'sqlite'], true)) {
                throw new RuntimeException('Phục hồi hiện hỗ trợ MySQL và SQLite.');
            }
            if ($matches[1] !== $driver) {
                throw new RuntimeException('Loại cơ sở dữ liệu trong file không khớp với ứng dụng.');
            }
            if (rewind($stream) === false) {
                throw new RuntimeException('Không thể đọc file sao lưu.');
            }

            return $this->inspectStatements($driver, $this->statementsFromStream($stream));
        } finally {
            fclose($stream);
        }
    }

    /** @param iterable<string> $statements */
    private function inspectStatements(string $driver, iterable $statements): array
    {
        $connection = DB::connection();

        $knownTables = $this->tableNames($connection, $driver);
        $count = 0;
        $tables = [];
        foreach ($statements as $statement) {
            $count++;
            if (! $this->isAllowedStatement($statement, $driver, $knownTables)) {
                throw new RuntimeException('File chứa câu lệnh SQL không được hỗ trợ.');
            }
            if (preg_match('/\ADROP\s+TABLE/i', $statement)) {
                throw new RuntimeException('Không hỗ trợ bản sao lưu xóa cấu trúc bảng. Hãy xuất lại bằng Vistiq hiện tại.');
            }
            if (preg_match('/\A(?:CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|DELETE\s+FROM|INSERT\s+INTO)\s+[`"]?([a-zA-Z0-9_]+)/i', $statement, $table)) {
                $tables[$table[1]] = true;
            }
        }
        if ($count < 3) {
            throw new RuntimeException('File sao lưu thiếu dữ liệu hoặc cấu trúc.');
        }

        return ['driver' => $driver, 'statements' => $count, 'tables' => array_keys($tables)];
    }

    private function createStatement(Connection $connection, string $table, string $driver): ?string
    {
        if ($driver === 'mysql') {
            $result = $connection->selectOne('SHOW CREATE TABLE '.$this->quoteIdentifier($table, $driver));

            return $result === null ? null : array_values((array) $result)[1];
        }

        return $connection->selectOne(
            'SELECT sql FROM sqlite_master WHERE type = ? AND name = ?',
            ['table', $table],
        )?->sql;
    }

    /** @return list<string> */
    private function tableNames(Connection $connection, string $driver): array
    {
        $schema = $driver === 'mysql' ? $connection->getDatabaseName() : null;

        return array_values(array_unique(
            $connection->getSchemaBuilder()->getTableListing(schema: $schema, schemaQualified: false),
        ));
    }

    private function quoteIdentifier(string $identifier, string $driver): string
    {
        $quote = $driver === 'mysql' ? '`' : '"';

        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }

    private function makeCreateTableNonDestructive(string $statement): string
    {
        return preg_replace('/\ACREATE\s+TABLE\s+/i', 'CREATE TABLE IF NOT EXISTS ', trim($statement), 1) ?? $statement;
    }

    private function makeCreateIndexNonDestructive(string $statement): string
    {
        return preg_replace('/\ACREATE\s+((?:UNIQUE\s+)?INDEX\s+)/i', 'CREATE $1IF NOT EXISTS ', trim($statement), 1) ?? $statement;
    }

    private function quoteValue(mixed $value, Connection $connection, string $driver): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $quoted = $connection->getPdo()->quote((string) $value);

        if ($quoted === false) {
            $hex = bin2hex((string) $value);

            return $driver === 'mysql' ? "X'{$hex}'" : "X'{$hex}'";
        }

        return $quoted;
    }

    /** @return iterable<string> */
    private function statements(string $sql): iterable
    {
        $stream = fopen('php://temp', 'w+b');
        if (! $stream || ! $this->writeStringToStream($stream, $sql) || rewind($stream) === false) {
            if (is_resource($stream)) {
                fclose($stream);
            }

            throw new RuntimeException('Không thể đọc file sao lưu.');
        }

        try {
            yield from $this->statementsFromStream($stream);
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function writeStringToStream($stream, string $value): bool
    {
        $offset = 0;
        $length = strlen($value);
        while ($offset < $length) {
            $written = fwrite($stream, substr($value, $offset, 65536));
            if ($written === false || $written === 0) {
                return false;
            }
            $offset += $written;
        }

        return true;
    }

    /** @param resource $stream
     *  @return iterable<string>
     */
    private function statementsFromStream($stream): iterable
    {
        $statement = '';
        $quote = null;
        $chunk = '';
        $offset = 0;
        $read = static function () use ($stream, &$chunk, &$offset): ?string {
            if ($offset >= strlen($chunk)) {
                $chunk = fread($stream, 65536);
                $offset = 0;
                if ($chunk === false || $chunk === '') {
                    return null;
                }
            }

            return $chunk[$offset++];
        };
        $current = $read();
        $nextCharacter = $read();
        $advance = static function (int $count = 1) use (&$current, &$nextCharacter, $read): void {
            for ($step = 0; $step < $count; $step++) {
                $current = $nextCharacter;
                $nextCharacter = $read();
            }
        };

        while ($current !== null) {
            $character = $current;

            if ($quote === null && $character === '-' && $nextCharacter === '-') {
                while ($current !== null && $current !== "\n") {
                    $advance();
                }
                if ($current === "\n") {
                    $advance();
                }

                continue;
            }

            if ($quote === null && $character === '#') {
                while ($current !== null && $current !== "\n") {
                    $advance();
                }
                if ($current === "\n") {
                    $advance();
                }

                continue;
            }

            if ($quote === null && $character === '/' && $nextCharacter === '*') {
                $advance(2);
                while ($current !== null && ! ($current === '*' && $nextCharacter === '/')) {
                    $advance();
                }
                if ($current !== null) {
                    $advance(2);
                }

                continue;
            }

            if ($quote !== null) {
                $statement .= $character;

                if ($character === '\\' && $quote !== '`') {
                    $statement .= $nextCharacter ?? '';
                    $advance(2);

                    continue;
                }

                if ($character === $quote) {
                    if ($nextCharacter === $quote) {
                        $statement .= $nextCharacter;
                        $advance(2);
                    } else {
                        $quote = null;
                        $advance();
                    }
                } else {
                    $advance();
                }

                continue;
            }

            if (in_array($character, ["'", '"', '`'], true)) {
                $quote = $character;
                $statement .= $character;
                $advance();

                continue;
            }

            if ($character === ';') {
                if (trim($statement) !== '') {
                    yield trim($statement);
                }

                $statement = '';
                $advance();

                continue;
            }

            $statement .= $character;
            $advance();
        }

        if ($quote !== null) {
            throw new RuntimeException('File SQL có chuỗi chưa đóng.');
        }
        if (trim($statement) !== '') {
            throw new RuntimeException('File SQL thiếu dấu kết thúc câu lệnh.');
        }
    }

    private function isAllowedStatement(string $statement, string $driver, array $knownTables): bool
    {
        if (preg_match($driver === 'mysql' ? '/\\ASET\\s+FOREIGN_KEY_CHECKS\\s*=\\s*[01]\\z/i' : '/\\APRAGMA\\s+foreign_keys\\s*=\\s*(?:ON|OFF)\\z/i', $statement)) {
            return true;
        }
        if ($driver === 'sqlite' && preg_match('/\\ACREATE\\s+(?:UNIQUE\\s+)?INDEX\\s+(?:IF\\s+NOT\\s+EXISTS\\s+)?["`]?([a-zA-Z0-9_]+)["`]?\\s+ON\\s+["`]?([a-zA-Z0-9_]+)["`]?\\s*\\((["`a-zA-Z0-9_,\\s]+)\\)\\z/i', $statement, $index)) {
            return in_array($index[2], $knownTables, true);
        }
        if (! preg_match('/\\A(DROP\\s+TABLE\\s+IF\\s+EXISTS|CREATE\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?|DELETE\\s+FROM|INSERT\\s+INTO)\\s+[`"]?([a-zA-Z0-9_]+)[`"]?(?=\\s|\\(|$)(.*)\\z/is', $statement, $matches)) {
            return false;
        }
        if (! in_array($matches[2], $knownTables, true)) {
            return false;
        }
        $tail = $matches[3];
        if (preg_match('/\\ADROP|\\ADELETE/i', $matches[1])) {
            return trim($tail) === '';
        }
        $unquoted = preg_replace('/\x27(?:\x27\x27|[^\x27])*\x27|"(?:""|[^"])*"|`[^`]*`/s', '', $tail);
        if (preg_match('/\\b(?:SELECT|UNION|OUTFILE|INFILE|LOAD_FILE|SLEEP|BENCHMARK|ATTACH|DETACH|TRIGGER|PROCEDURE|FEDERATED|DIRECTORY)\\b/i', $unquoted)) {
            return false;
        }
        if (str_starts_with(strtoupper($matches[1]), 'INSERT') && preg_match('/[A-Za-z_][A-Za-z_0-9]*\\s*\\(/', preg_replace('/\\AVALUES\\b/i', '', substr($unquoted, strpos(strtoupper($unquoted), 'VALUES'))))) {
            return false;
        }
        $allowed = $driver === 'mysql'
            ? '/\A(?:SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]|DROP\s+TABLE\s+IF\s+EXISTS\s+|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+|DELETE\s+FROM\s+|INSERT\s+INTO\s+)/i'
            : '/\A(?:PRAGMA\s+foreign_keys\s*=\s*(?:ON|OFF)|DROP\s+TABLE\s+IF\s+EXISTS\s+|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+|DELETE\s+FROM\s+|INSERT\s+INTO\s+)/i';

        return preg_match($allowed, ltrim($statement)) === 1;
    }
}
