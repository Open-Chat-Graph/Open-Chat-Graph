<?php

use PHPUnit\Framework\TestCase;

class ZipFileTest extends TestCase
{
    function test()
    {
        $zipFile = new \PhpZip\ZipFile();
        try {
            $zipFile
                ->addFilesFromGlobRecursive(__DIR__ . '/../public/oc-img/', '*.*') // add files from the directory
                ->saveAsFile('img.zip') // save the archive to a file
                ->close(); // close archive
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }

        $this->assertTrue(true);
    }
}
