<?php

declare(strict_types=1);

use App\Config\AppConfig;
use App\Models\Accreditation\AccreditationDB;
use App\Models\Importer\SqlInsertWithBindValue;
use PHPUnit\Framework\TestCase;

class AdminToolTest extends TestCase
{
    public SqlInsertWithBindValue $sqlInsertWithBindValue;

    public function setUp(): void
    {
        $this->sqlInsertWithBindValue = app(SqlInsertWithBindValue::class);
    }

    public function test()
    {
        $examFile = AppConfig::ACCREDITATION_DATA_FILE_PATH . '/exam/exam.dat';
        $userFile = AppConfig::ACCREDITATION_DATA_FILE_PATH . '/user/user.dat';

        $a = getUnserializedFile(
            $examFile,
            true
        );

        //$this->sqlInsertWithBindValue->import(AccreditationDB::connect(), 'exam', $a);

        $b = getUnserializedFile(
            $userFile,
            true
        );

       // $this->sqlInsertWithBindValue->import(AccreditationDB::connect(), 'user', $b);

        $this->assertTrue(true);
    }
}
