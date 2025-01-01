<?php

namespace Shadow;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */
interface DBInterface
{
    /**
     * Represents a connection between PHP and a database server.
     * 
     * @return \PDO
     * 
     * @throws PDOException — if the attempt to connect to the requested database fails.
     * @throws InvalidArgumentException — if database configuration is invalid.
     * 
     * @param ?array{host?: string, dbName?: string, userName?: string, password?: string, charset?: string, attrPersistent?: bool, spliteFilePath?: bool} $config
     */
    public static function connect(?array $config = null): \PDO;

    /**
     * @param string $query
     * @param array  $options [optional] This array holds one or more key=>value pairs to set attribute values for the PDOStatement object that this method returns.  
     *                        You would most commonly use this to set the PDO::ATTR_CURSOR value to PDO::CURSOR_SCROLL to request a scrollable cursor. Some drivers have driver specific options that may be set at prepare-time.
     *
     * @return PDOStatement|false If the database server successfully prepares the statement, PDO::prepare returns a PDOStatement object.  
     *                            If the database server cannot successfully prepare the statement, PDO::prepare returns FALSE or emits PDOException (depending on error handling).
     */
    public static function prepare(string $query, array $options = []): \PDOStatement|false;

    /**
     * Executes an SQL query and returns a PDOStatement object with bound values.
     *
     * @param string     $query  The SQL query to execute.
     *                   * *Example:* `'SELECT * FROM table WHERE category = :category LIMIT :offset, :limit'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     *                           InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     *                   * *Example:* `['category' => 'foods', 'limit' => 20, 'offset' => 60]`
     * 
     * @return PDOStatement Returns a PDOStatement object containing the results of the query, or false.
     * 
     * @throws PDOException If an error occurs during the query execution.
     * @throws InvalidArgumentException If any of the array values are not strings, numbers or bool.
     */
    public static function execute(string $query, ?array $params = null): \PDOStatement;

    /**
     * Executes a callback function within a transaction.
     * Rolls back the transaction and throws an exception on failure.
     *
     * @param callable $callback The callback function to execute.
     * @return mixed The return value of the callback function.
     * 
     * @throws \Throwable
     */
    public static function transaction(callable $callback): mixed;

    /**
     * Executes an SQL query and returns a single row as an associative array.
     * 
     * @param string     $query  The SQL query to execute.
     *                   * *Example:* `'SELECT * FROM table WHERE id = :id'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     *                           InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     *                   * *Example:* `['id' => 10]`
     * 
     * @return array|false Returns a single row as an associative array or false if no rows.
     * 
     * @throws PDOException If an error occurs during the query execution.
     * @throws InvalidArgumentException If any of the array values are not strings, numbers or bool.
     */
    public static function fetch(string $query, ?array $params = null, array $args = [\PDO::FETCH_ASSOC]): array|object|false;

    /**
     * Executes an SQL query and returns rows as associative arrays.
     * 
     * @param string     $query  The SQL query to execute.
     *                   * *Example:* `'SELECT * FROM table WHERE category = :category LIMIT :offset, :limit'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     *                           InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     *                   * *Example:* `['category' => 'foods', 'limit' => 20, 'offset' => 60]`
     * 
     * @return array An empty array is returned if there are zero results to fetch.
     * 
     * @throws PDOException If an error occurs during the query execution.
     * @throws InvalidArgumentException If any of the array values are not strings, numbers or bool.
     */
    public static function fetchAll(string $query, ?array $params = null, array $args = [\PDO::FETCH_ASSOC]): array;

    public static function fetchColumn(string $query, ?array $params = null): mixed;

    /**
     *　Executes an SQL query and returns the ID of the last inserted row or sequence value.
     * 
     * @param string $query The SQL query to execute.
     * * *Example:* `'INSERT INTO user (name) SELECT :name'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     * \InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     * * *Example:* `['name' => 'mimikyu']`
     * 
     * @return int Returns the row ID of the last row that was inserted into the database.
     * 
     * @throws \PDOException If an error occurs during the query execution.
     * @throws \InvalidArgumentException If any of the array values are not strings, numbers or bool.
     */
    public static function executeAndGetLastInsertId(string $query, ?array $params = null): int;

    /**
     * Executes an SQL UPDATE query and checks if any rows were affected.
     * 
     * @param string $query The SQL UPDATE query to execute.
     * * *Example:* `'UPDATE users SET status = :newStatus WHERE id = :userId'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     * \InvalidArgumentException will be thrown if any of the array values are not strings, numbers, or bool.
     * * *Example:* `['newStatus' => 'active', 'userId' => 123]`
     * 
     * @return bool Returns `true` if any rows were affected by the UPDATE query, `false` otherwise.
     * 
     * @throws \PDOException If an error occurs during the query execution.
     * @throws \InvalidArgumentException If any of the array values are not strings, numbers, or bool.
     */
    public static function executeAndCheckResult(string $query, ?array $params = null): bool;

    /**
     * Executes a LIKE search query and returns a \PDOStatement object with bound values.
     * 
     * @param callable $query A function that returns a string representing the SQL query. 
     * * *Example:* `fn (string $where): string => "SELECT * FROM table {$where} AND category = :category LIMIT :offset, :limit"`
     * 
     * @param callable $whereClauseQuery A function that returns a string representing the WHERE clause.
     * * *Example:* `fn (int $i): string => "(title LIKE :keyword{$i} OR text LIKE :keyword{$i})"`
     * 
     * @param string $keyword The keyword(s) to search for.
     * \InvalidArgumentException will be thrown if the string is empty or only contains whitespace characters.
     * * *Example:* `'Split keywords by whitespace and search with LIKE'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     * \InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     * * *Example:* `['category' => 'foods', 'limit' => 20, 'offset' => 60]`
     * 
     * @param string $whereClausePlaceholder [optional] Placeholder for keyword in WHERE clause.
     * * *Example:* `'keyword'`
     * 
     * @param array|null $affix [optional] An array containing prefix and suffix strings for keywords.
     * * *Example:* `['%', '%']`
     * 
     * @param int $fetchAllMode [optional] PDO fetch mode.
     * * *Example:* `\PDO::FETCH_FUNC`
     * 
     * @param array $fetchAllArgs [optional] Additional arguments for fetchAll method.
     * * *Example:* `[$callback]`
     * 
     * @return array An empty array is returned if there are zero results to search.
     * 
     * @throws \PDOException If an error occurs during the query execution.
     * @throws \LogicException If any of the given callbacks are invalid.
     * @throws \InvalidArgumentException If any of the parameter values are invalid or the given callbacks are invalid.
     */
    public static function executeLikeSearchQuery(
        callable $query,
        callable $whereClauseQuery,
        string $keyword,
        ?array $params = null,
        ?array $affix = ['%', '%'],
        array $fetchAllArgs = [\PDO::FETCH_ASSOC],
        string $whereClausePlaceholder = 'keyword',
    ): array;

    /**
     * Executes a full-text search query and returns the result as an array.
     *
     * @param callable $query A function that returns a string representing the SQL query. 
     * * *Example:* `fn (string $where): string => "SELECT * FROM table {$where} AND category = :category LIMIT :offset, :limit"`
     * 
     * @param string $whereClauseQuery The SQL query with a placeholder for the search keyword.
     * * *Example:* `'WHERE MATCH(title, text) AGAINST(:search IN BOOLEAN MODE)'`
     * 
     * @param string $keyword The search keyword to be used in the full-text search query.
     * \InvalidArgumentException will be thrown if the string is empty or only contains whitespace characters.
     * * *Example:* `'Splits keywords by whitespace'`
     * 
     * @param array|null $params [optional] An associative array of query parameters.
     * \InvalidArgumentException will be thrown if any of the array values are not strings or numbers.
     * * *Example:* `['category' => 'foods', 'limit' => 20, 'offset' => 60]`
     * 
     * @return array An array of rows returned by the query. An empty array is returned if there are no results to search.
     * 
     * @throws \PDOException If an error occurs during the query execution.
     * @throws \LogicException If any of the given callbacks are invalid.
     * @throws \InvalidArgumentException If the search keyword is empty or the WHERE clause query has an invalid placeholder.
     */
    public static function executeFulltextSearchQuery(
        callable $query,
        string $whereClauseQuery,
        string $keyword,
        ?array $params = null
    ): array;
}
