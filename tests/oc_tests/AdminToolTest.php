<?php

declare(strict_types=1);

use App\Config\AdminConfig;
use App\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use Spatie\SchemaOrg\Schema;

class AdminToolTest extends TestCase
{
    public function test()
    {
        function generate_article_schema(
            string $headline,
            string $description,
            string $datePublished,
            string $dateModified,
            string $authorName,
            string $authorUrl,
            string $questionName,
            string $acceptedAnswerText,
            array $suggestedAnswers,
        ): array {
            return Schema::article()
                ->headline($headline)
                ->description($description)
                ->datePublished($datePublished)
                ->dateModified($dateModified)
                ->author(
                    Schema::person()
                        ->name($authorName)
                        ->url($authorUrl)
                )
                ->mainEntity(
                    Schema::question()
                        ->name($questionName)
                        ->acceptedAnswer(
                            Schema::answer()
                                ->text($acceptedAnswerText)
                        )
                        ->suggestedAnswer(
                            array_map(function ($answerText) {
                                return Schema::answer()->text($answerText);
                            }, $suggestedAnswers)
                        )
                )
                ->toArray();
        }

        // Example usage:
        $articleSchema = generate_article_schema(
            'クイズのタイトル',
            'クイズの説明',
            '作成日時',
            '更新日時',
            '出題者の名前',
            '出題者のURL',
            'ここに問題文が入ります。',
            '選択肢2',
            ['選択肢1', '選択肢3', '選択肢4']
        );

        // Convert to JSON
        $jsonSchema = json_encode($articleSchema, JSON_PRETTY_PRINT);

        echo $jsonSchema;
    }
}
