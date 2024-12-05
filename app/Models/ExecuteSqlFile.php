<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Repositories\DB;
use Shadow\DBInterface;

class ExecuteSqlFile
{
    /**
     * @return int returns the number of rows that were modified or deleted by the SQL statement.  
     *             If no rows were affected, returns 0.
     */
    public function execute(string $string): int
    {
        return DB::transaction(fn () => $this->execQueries($string, new DB));
    }

    public function execQueries(string $string, DBInterface $db): int
    {
        $sqlQueries = explode(';', $string);

        foreach ($sqlQueries as $query) {
            if (trim($query) === '') {
                continue;
            }

            $rowCount = $db->connect()->exec($query);
        }

        return $rowCount;
    }
}
