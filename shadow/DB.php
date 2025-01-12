<?php

declare(strict_types=1);

namespace Shadow;

use Shared\MimimalCmsConfig;

/**
 * \PDO wrapper class for SQL databases
 * 
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
class DB implements DBInterface
{
    public static ?\PDO $pdo = null;

    /**
     * @param ?array{dbHost?: string, dbName?: string, dbUserName?: string, dbPassword?: string, charset?: string, attrPersistent?: bool} $config
     */
    public static function connect(?array $config = null): \PDO
    {
        if (static::$pdo !== null) {
            return static::$pdo;
        }

        $host = $config['dbHost'] ?? MimimalCmsConfig::$dbHost;
        $dbName = $config['dbName'] ?? MimimalCmsConfig::$dbName;
        $userName = $config['dbUserName'] ?? MimimalCmsConfig::$dbUserName;
        $password = $config['dbPassword'] ?? MimimalCmsConfig::$dbPassword;
        $attrPersistent = $config['attrPersistent'] ?? (MimimalCmsConfig::$dbAttrPersistent ?? false);
        $charset = $config['charset']  ?? (MimimalCmsConfig::$dbCharset ?? 'utf8mb4');

        static::$pdo = new \PDO(
            'mysql:host=' . $host . ';dbname=' .$dbName . ';charset=' . $charset,
            $userName,
            $password,
            [\PDO::ATTR_PERSISTENT => $attrPersistent]
        );

        // Enable \PDO to throw exceptions on error.
        static::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return static::$pdo;
    }

    public static function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (static::$pdo === null) {
            static::connect();
        }

        return static::$pdo->prepare($query, $options);
    }

    public static function execute(string $query, ?array $params = null): \PDOStatement
    {
        if (static::$pdo === null) {
            static::connect();
        }

        $stmt = static::$pdo->prepare($query);

        if ($params === null) {
            $stmt->execute();
        } else {
            foreach ($params as $key => $value) {
                if ($value === null) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_NULL);
                } elseif (is_bool($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_BOOL);
                } elseif (is_numeric($value)) {
                    $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
                    $stmt->bindValue($key, $value, $type);
                } elseif (is_string($value)) {
                    $stmt->bindValue($key, $value, \PDO::PARAM_STR);
                } else {
                    throw new \InvalidArgumentException("Only string, number, null or bool is allowed: {$key}");
                }
            }

            $stmt->execute();
        }

        return $stmt;
    }

    public static function transaction(callable $callback): mixed
    {
        if (static::$pdo === null) {
            static::connect();
        }

        try {
            static::$pdo->beginTransaction();
            $result = $callback(static::$pdo);
            static::$pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            static::$pdo->rollBack();
            throw $e;
        }
    }

    public static function fetch(string $query, ?array $params = null, array $args = [\PDO::FETCH_ASSOC]): array|object|false
    {
        if (static::$pdo === null) {
            static::connect();
        }

        $firstArg = $args[0] ?? 0;
        if ($firstArg < \PDO::FETCH_CLASS || ($firstArg !== \PDO::FETCH_CLASS && $firstArg <= 10)) {
            return static::execute($query, $params)->fetch(...$args);
        }

        $sth = static::execute($query, $params);
        $sth->setFetchMode(...$args);
        return $sth->fetch();
    }

    public static function fetchAll(string $query, ?array $params = null, array $args = [\PDO::FETCH_ASSOC]): array
    {
        if (static::$pdo === null) {
            static::connect();
        }

        return static::execute($query, $params)->fetchAll(...$args);
    }

    public static function fetchColumn(string $query, ?array $params = null): mixed
    {
        if (static::$pdo === null) {
            static::connect();
        }

        return static::execute($query, $params)->fetchColumn();
    }

    public static function executeAndGetLastInsertId(string $query, ?array $params = null): int
    {
        if (static::$pdo === null) {
            static::connect();
        }

        static::execute($query, $params);
        return (int) static::$pdo->lastInsertId();
    }

    public static function executeAndCheckResult(string $query, ?array $params = null): bool
    {
        if (static::$pdo === null) {
            static::connect();
        }

        return static::execute($query, $params)->rowCount() > 0;
    }

    public static function executeLikeSearchQuery(
        callable $query,
        callable $whereClauseQuery,
        string $keyword,
        ?array $params = null,
        ?array $affix = ['%', '%'],
        array $fetchAllArgs = [\PDO::FETCH_ASSOC],
        string $whereClausePlaceholder = 'keyword',
        string $whereClausePrefix = 'WHERE '
    ): array {
        if (static::$pdo === null) {
            static::connect();
        }

        $convertedKeyword = static::escapeLike(
            preg_replace('/　/u', ' ', mb_convert_encoding($keyword, 'UTF-8', 'auto'))
        );

        $splitKeywords = explode(' ', $convertedKeyword);

        $whereClauses = [];
        foreach ($splitKeywords as $i => $keyword) {
            $whereClauses[] = $whereClauseQuery($i);
        }

        $whereClause = $whereClausePrefix . implode(' AND ', $whereClauses);

        $queryResult = $query($whereClause);
        if (!is_string($queryResult)) {
            throw new \LogicException('Query callback must return a string');
        }

        $stmt = static::$pdo->prepare($queryResult);

        foreach ($splitKeywords as $i => $word) {
            $word = ($affix[0] ?? '') . $word . ($affix[1] ?? '');
            $stmt->bindValue($whereClausePlaceholder . $i, $word, \PDO::PARAM_STR);
        }

        if ($params === null) {
            $stmt->execute();
            return $stmt->fetchAll(...$fetchAllArgs);
        }

        foreach ($params as $key => $value) {
            if (!is_string($value) && !is_numeric($value)) {
                throw new \InvalidArgumentException(
                    "Invalid parameter value for key {$key}: only strings and numbers are allowed."
                );
            }

            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $type);
        }

        $stmt->execute();
        return $stmt->fetchAll(...$fetchAllArgs);
    }

    /**
     * Escapes special characters in a string for use in LIKE clause.
     *
     * @param string $value The string to be escaped.
     * @param string $char The escape character to use (defaults to backslash).
     * @return string The escaped string.
     */
    protected static function escapeLike(string $value, string $char = '\\'): string
    {
        $search  = [$char, '%', '_'];
        $replace = [$char . $char, $char . '%', $char . '_'];

        return str_replace($search, $replace, $value);
    }

    public static function executeFulltextSearchQuery(
        callable $query,
        string $whereClauseQuery,
        string $keyword,
        ?array $params = null
    ): array {
        if (static::$pdo === null) {
            static::connect();
        }

        if (!preg_match('{:\w+}', $whereClauseQuery, $matches)) {
            throw new \InvalidArgumentException('Invalid placeholder for WHERE clause.');
        }

        $params[$matches[0]] = static::fulltextSearchParam($keyword);

        $queryResult = $query($whereClauseQuery);
        if (!is_string($queryResult)) {
            throw new \LogicException('Query callback must return a string');
        }

        return static::fetchAll($queryResult, $params);
    }

    public static function fulltextSearchParam(string $keyword): string
    {
        $convertedKeyword = preg_replace('/　/u', ' ', mb_convert_encoding($keyword, 'UTF-8', 'auto'));

        if (empty(trim($convertedKeyword))) {
            throw new \InvalidArgumentException('Please provide a non-empty search keyword.');
        }

        $param = '';
        foreach (explode(' ', $convertedKeyword) as $i => $word) {
            if ($i > 0) {
                $param .= ' ';
            }

            $param .= '+' . static::escapeFullTextSearch($word);
        }

        return $param;
    }

    /**
     * Escape a string for use in a MySQL full-text search query.
     *
     * MySQL full-text search requires specific characters to be escaped for safe usage in queries.
     * This function provides a basic example, and the actual escape requirements may vary depending
     * on the specific use case and requirements.
     *
     * @param string $input The input string to be escaped for full-text search.
     * @return string The escaped input string ready for use in a MySQL full-text search query.
     */
    protected static function escapeFullTextSearch($input)
    {
        // Escape specific characters for MySQL full-text search
        $escapedInput = str_replace(['+', '-', '<', '>', '(', ')', '~', '*', '"', '@'], ['\+', '\-', '\<', '\>', '\(', '\)', '\~', '\*', '\"', '\@'], $input);

        // Enclose AND, OR, NOT, etc., in backticks to allow their use in the search expression
        $escapedInput = preg_replace('/\b(AND|OR|NOT)\b/', '`$1`', $escapedInput);

        return $escapedInput;
    }
}
