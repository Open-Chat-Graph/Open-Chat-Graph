<?php

declare(strict_types=1);

namespace Shadow\File;

use PHPUnit\Framework\TestCase;
use Shared\Exceptions\ValidationException;

class FileValidatorTest extends TestCase
{
    private FileValidator $fileValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileValidator = new FileValidator();
    }

    public function testValidate(): void
    {
        $fileData = file_get_contents('/var/www/html/image.jpg');
        $maxFileSize = 1; // 1KB
        $allowedMimeTypeArray = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/webp' => ['webp']
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File too large:');

        $this->fileValidator->validate($fileData, $maxFileSize, $allowedMimeTypeArray);
    }
}
