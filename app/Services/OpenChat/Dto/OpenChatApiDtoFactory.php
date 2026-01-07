<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Dto;

use Shadow\Kernel\Validator;

class OpenChatApiDtoFactory
{
    /**
     * @param \Closure $callback `$callback(OpenChatDto): string|null　error文字列` 1件毎に呼び出されるコールバック。
     * 
     * @return array `[string]` Errors 
     */
    function validateAndMapToOpenChatDto(array $apiData, ?\Closure $callback = null): array
    {
        $errors = [];

        $categoryId = Validator::num($apiData['squaresByCategory'][0]['category']['id'] ?? false);
        if ($categoryId === false) {
            $jsonString = json_encode($apiData, JSON_UNESCAPED_UNICODE);
            $errors[] = "OpenChatApiDataエラー: categoryの要素がありません: {$jsonString}";

            return $errors;
        }

        $squares = $apiData['squaresByCategory'][0]['squares'];

        foreach ($squares as $square) {
            try {
                $openChatDto = $this->validateAndMapToOpenChatApiDtoFromSquare($square, $categoryId);
            } catch (\RuntimeException $e) {
                $jsonString = json_encode($apiData, JSON_UNESCAPED_UNICODE);
                $errors[] = $e->__toString() . ": {$jsonString}";

                continue;
            }

            $error = $callback($openChatDto);
            if (Validator::str($error)) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    /**
     * @throws \RuntimeException
     */
    private function validateAndMapToOpenChatApiDtoFromSquare(array $square, int $categoryId): OpenChatDto
    {
        $exceptionClass = \RuntimeException::class;

        $dto = new OpenChatDto;
        $dto->category = ($categoryId !== 0) ? $categoryId : null;

        try {
            $dto->emid = Validator::str($square['square']['emid'], e: $exceptionClass);
            $dto->name = Validator::str($square['square']['name'], emptyAble: true, e: $exceptionClass);
            $dto->desc = Validator::str($square['square']['desc'], emptyAble: true, e: $exceptionClass);
            $dto->profileImageObsHash = Validator::str($square['square']['profileImageObsHash'], e: $exceptionClass);
            $dto->memberCount = Validator::num($square['memberCount'], e: $exceptionClass);
            $dto->emblem = Validator::num($square['square']['emblems'][0] ?? null, emptyAble: true, default: 0, e: $exceptionClass);
            $dto->joinMethodType = Validator::num($square['square']['joinMethodType'], e: $exceptionClass);

            if (isset($square['createdAt'])) {
                $createdAt = Validator::num($square['createdAt'], e: $exceptionClass);
                $dto->createdAt = (int)floor($createdAt / 1000);
            }
        } catch (\Throwable $e) {
            $jsonString = json_encode($square, JSON_UNESCAPED_UNICODE);
            throw new $exceptionClass($e->__toString() . ": {$jsonString}");
        }

        return $dto;
    }
}
