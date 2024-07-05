<?php

declare(strict_types=1);

namespace App\Services\Accreditation\QuizApi;

use App\Services\Accreditation\QuizApi\Dto\Question\Result;
use Shared\Exceptions\ValidationException;

class QuizResultUrlService
{
    /**
     * @param Result[] $result
     */
    function generate(array $result, int $totalQuestions, int $totalScore, int $totalTime, string $selectedQuizTopic, string $name)
    {
        $hashedResult = [];
        foreach ($result as $q) {
            $hash = hash('crc32', json_encode([$q->choices, $q->question, $q->correctAnswers, $hash ?? '']));
            $hash = substr($hash, 0, 4);

            $selected = array_search($q->selectedAnswer, $q->choices);
            if (!$selected)
                throw new ValidationException("不正な回答データ\n" . 'selected is false');

            try {
                $hashedResult[] = new class($q->id, $hash, $selected)
                {
                    function __construct(
                        public int $i,
                        public string $h,
                        public int $s,
                    ) {
                    }
                };
            } catch (\Throwable $e) {
                throw new ValidationException("不正な回答データ\n" . $e->getMessage());
            }
        }

        $data = [
            'r' => $hashedResult,
            'tq' => $totalQuestions,
            'ts' => $totalScore,
            'tt' => $totalTime,
            'tp' => $selectedQuizTopic,
            'nm' => $name
        ];

        $dataHash = hash('crc32', json_encode($data) . 'pikachu');
        $dataHash = substr($dataHash, 0, 4);

        return json_encode(['d' => $data, 'h' => $dataHash]);
    }
}
