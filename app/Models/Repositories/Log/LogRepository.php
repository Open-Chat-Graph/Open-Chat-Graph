<?php

declare(strict_types=1);

namespace App\Models\Repositories\Log;

use App\Models\Repositories\DB;

class LogRepository implements LogRepositoryInterface
{
    public function logAddOpenChat(int $open_chat_id, string $ip, string $ua): void
    {
        $this->insertLog('AddOpenChat', $ip, $ua, (string)$open_chat_id);
    }

    public function logUpdateOpenChatError(int $open_chat_id, string $message): void
    {
        $this->insertLog('UpdateOpenChat', 'null', 'null', "id: {$open_chat_id} error: {$message}");
    }

    public function logOpenChatImageStoreError(string $imgIdentifier, string $message): void
    {
        $this->insertLog('OpenChatImageStoreError', 'null', 'null', "imgIdentifier: {$imgIdentifier} error: {$message}");
    }

    public function logAddOpenChatError(string $ip, string $ua, string $message): void
    {
        $this->insertLog('AddOpenChatError', $ip, $ua, $message);
    }

    protected function insertLog(string $type, string $ip, string $ua, string $message = ''): void
    {
        $query =
            'INSERT INTO
                user_log (type, message, ip, ua)
            VALUES
                (:type, :message, :ip, :ua)';

        DB::execute($query, compact('type', 'ip', 'ua', 'message'));
    }

    public function getNumAddOpenChatPerMinute(string $ip): int
    {
        $query =
            "SELECT
                count(id) AS count
            FROM
                user_log
            WHERE
                (type = 'AddOpenChatError' OR type = 'AddOpenChat' OR type = 'AddOpenChatDuplicationError')
                AND ip = :ip
                AND time > CURRENT_TIMESTAMP - INTERVAL 60 SECOND";

        return (int)DB::fetchColumn($query, compact('ip'));
    }

    public function getRecentLog(): string
    {
        $query =
            "SELECT
                message
            FROM
                user_log
            ORDER BY
                id DESC
            LIMIT 1";

        $result = DB::fetchColumn($query);
        if (!$result) {
            return '';
        }

        return $result;
    }
}
