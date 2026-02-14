<?php

declare(strict_types=1);

namespace App\Models\SQLite;

use App\Models\Importer\AbstractSqlImporter;

class SQLiteInsertImporter extends AbstractSqlImporter
{
    private const MAX_RETRIES = 5;
    private const RETRY_USLEEP_TIME = 100000; // 0.1 seconds
    private const RETRYABLE_ERRORS = [
        'database disk image is malformed',
        'database is locked'
    ];

    /**
     * @throws \RuntimeException
     * @throws \PDOException
     */
    function importWithKeys(\PDO $pdo, string $tableName, array $keys, array $data, int $chunkSize = 2000): int
    {
        if (empty($data)) {
            return 0;
        }

        $rowCount = 0;
        foreach (array_chunk($data, $chunkSize) as $chunk) {
            $rowCount += $this->importProsess($pdo, $keys, $chunk, $tableName);
        }

        return $rowCount;
    }

    protected function importProsess(\PDO $pdo, array $keys, array $chunk, string $tableName): int
    {
        return $this->executeWithRetry($pdo, $keys, $chunk, $tableName);
    }

    private function executeWithRetry(\PDO $pdo, array $keys, array $chunk, string $tableName): int
    {
        $attempts = 0;
        $result = false;
        $lastException = null;
        $rowCount = 0;
        
        while ($attempts < self::MAX_RETRIES && !$result) {
            try {
                $rowCount = $this->execute($pdo, $keys, $chunk, $tableName);
                $result = true;
            } catch (\PDOException $e) {
                $shouldRetry = false;
                foreach (self::RETRYABLE_ERRORS as $error) {
                    if (str_contains($e->getMessage(), $error)) {
                        $shouldRetry = true;
                        break;
                    }
                }

                if (!$shouldRetry) {
                    throw $e;
                }

                $lastException = $e;
                usleep(self::RETRY_USLEEP_TIME); // Wait for 0.1 seconds
                $attempts++;
            }
        }

        if (!$result) {
            throw $lastException ?? new \RuntimeException('Failed to execute import due to unknown error');
        }

        return $rowCount;
    }

    protected function buildQuery(array $keys, array $chunk, string $tableName): string
    {
        $columns = implode(',', $keys);
        $columns = "({$columns})";

        $values = implode(',', array_map(fn ($row) => "(" . implode(",", array_map(fn ($value) => $value === null ? 'NULL' : (is_int($value) ? $value : "'{$value}'"), $row)) . ")", $chunk));

        $query = "INSERT OR IGNORE INTO {$tableName} {$columns} VALUES {$values}";

        return $query;
    }
}
