<?php

namespace App\Services\OpenChat\Utility;

use App\Config\AppConfig;
use App\Services\OpenChat\Dto\OpenChatDto;
use Shared\MimimalCmsConfig;

class OpenChatServicesUtility
{
    private static ?\DateTime $date = null;

    /**
     * @return bool 収集を拒否している場合は true
     */
    static function containsHashtagNolog(OpenChatDto $dto): bool
    {
        //return strpos($desc, '#nolog') !== false;
        return false;
    }

    /**
     * @return string Y-m-d
     */
    static function getCronModifiedStatsMemberDate(): string
    {
        if (!self::$date) {
            self::$date = self::getCronModifiedDate(new \DateTime());
        }

        return self::$date->format('Y-m-d');
    }

    static function getCronModifiedDate(\DateTime $date): \DateTime
    {
        $date->setTimeZone(new \DateTimeZone('Asia/Tokyo'));

        if (MimimalCmsConfig::$urlRoot === '') {
            if ((int)$date->format('H') < AppConfig::CRON_MERGER_HOUR_RANGE_START['']) {
                $date->modify('-1 day');
            } else if ((int)$date->format('i') < AppConfig::CRON_START_MINUTE['']) {
                $date->modify('-1 day');
            }
        } else {
            $date->modify('-1 day');
        }

        return $date;
    }

    static function getModifiedCronTime(string|int $time): \DateTime
    {
        $fileTime = new \DateTime(is_int($time) ? '@' . $time : $time);
        $fileTime->setTimeZone(new \DateTimeZone('Asia/Tokyo'));

        if ((int)$fileTime->format('i') < AppConfig::CRON_START_MINUTE) {
            $fileTime->modify('-1 hour');
        }

        $fileTime->setTime((int)$fileTime->format('H'), AppConfig::CRON_START_MINUTE);

        return $fileTime;
    }
}
