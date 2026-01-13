<?php

declare(strict_types=1);

namespace App\Services\OpenChat\Dto;

use Shadow\Kernel\Validator;
use App\Config\OpenChatCrawlerConfigInterface;

class OpenChatCrawlerDtoFactory
{
    function __construct(
        private OpenChatCrawlerConfigInterface $config
    ) {
    }
    /**
     * @throws \RuntimeException
     */
    function validateAndMapToDto(string $invitationTicket, mixed $name, mixed $img_url, mixed $description, mixed $member): OpenChatDto
    {
        $exceptionClass = \RuntimeException::class;

        $dto = new OpenChatDto;
        $dto->invitationTicket = $invitationTicket;

        $dto->name = Validator::str($name, emptyAble: true, e: $exceptionClass);
        $dto->desc = Validator::str($description, emptyAble: true, e: $exceptionClass);

        $img_url = Validator::str($img_url, e: $exceptionClass);
        $dto->profileImageObsHash = str_replace($this->config->getLineImgUrl(), '', $img_url);

        $member = Validator::str($member, e: $exceptionClass);
        $member = str_replace(',', '', str_replace('Members ', '', $member));
        $dto->memberCount = Validator::num($member, min: 1, e: $exceptionClass);

        return $dto;
    }
}
