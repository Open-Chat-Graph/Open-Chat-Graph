<?php

declare(strict_types=1);

namespace App\Services\Accreditation\QuizApi\Dto\Question;

class Result
{
    /**
     * @param string[] $choices
     */
    public function __construct(
        public string $question,
        public array $choices,
        public array $correctAnswers,
        public int $score,
        public Contributor $contributor,
        public string $explanation,
        public Source $source,
        public int $id,
        public string $selectedAnswer,
        public bool $isMatch,
    ) {
    }
}
