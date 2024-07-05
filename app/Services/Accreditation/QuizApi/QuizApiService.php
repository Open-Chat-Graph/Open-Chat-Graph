<?php

declare(strict_types=1);

namespace App\Services\Accreditation\QuizApi;

use App\Middleware\RedirectLineWebBrowser;
use App\Models\Accreditation\QuizApiModel;
use App\Services\Accreditation\Dto\QuizApiQuestionDto;
use App\Services\Accreditation\Enum\ExamType;
use App\Services\Accreditation\QuizApi\Dto\Question\Contributor;
use App\Services\Accreditation\QuizApi\Dto\Question\Question;
use App\Services\Accreditation\QuizApi\Dto\Question\Source;
use App\Services\Accreditation\QuizApi\Dto\Topic;

class QuizApiService
{
    private const DEFAULT_SOURCE_TITLE = '安心・安全ガイドライン | LINEオープンチャット';
    private const DEFAULT_SOURCE_URL = 'https://openchat-jp.line.me/other/guideline';

    function __construct(
        private QuizApiModel $model,
        private RedirectLineWebBrowser $redirectLineWebBrowser
    ) {
    }

    private function addOpenExternalBrowser(string $url): string
    {
        if (!$this->redirectLineWebBrowser->isLineWebBrowser())
            return $url;

        if (strpos($url, '?') !== false) {
            return $url . '&openExternalBrowser=1&redirected=1';
        } else {
            return $url . '?openExternalBrowser=1&redirected=1';
        }
    }

    /**
     *  @param QuizApiQuestionDto[] $dbDtos
     * 
     *  @return Question[]
     */
    function buildQuestions(array $dbDtos): array
    {
        $questions = [];
        foreach ($dbDtos as $dto) {
            $contributor = new Contributor(
                $dto->user_name,
                $dto->room_name,
                $dto->room_url
            );

            $source = new Source(
                $dto->explanationArray['source_title'] ?: self::DEFAULT_SOURCE_TITLE,
                $this->addOpenExternalBrowser($dto->explanationArray['source_url'] ?: self::DEFAULT_SOURCE_URL),
            );

            $choices = [];
            foreach (range('a', 'd') as $key) {
                $choices[] = $dto->answersArray[$key];
            }

            $questions[] = new Question(
                $dto->question,
                $choices,
                [$dto->answersArray[$dto->answersArray['correct']]],
                1,
                $contributor,
                $dto->explanationArray['explanation'],
                $source,
                $dto->id
            );
        }

        return $questions;
    }

    /**
     *  @return Question[]
     */
    function buildSampleQuestions(): array
    {
        return [new Question(
            'サンプル問題(出題中の問題無し) オープンチャット利用時に守るべきことは？',
            [
                '毎日24時間以上参加すること',
                '友達を仲間はずれにすること',
                '安心・安全ガイドラインを守ること',
                '自分のプライバシーを無視すること'
            ],
            ['安心・安全ガイドラインを守ること'],
            1,
            new Contributor(
                'サンプルさん',
                '🔰オプチャの相談室🔰LINE公認🏅超初心者も管理人も副官も質問・宣伝・雑談OK‼︎サポートルーム',
                'https://line.me/ti/g2/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ'
            ),
            'ガイドラインを守ることは、オープンチャットのユーザーの安全を確保し、公平な利用環境を守るために重要です。これにより、トラブルを避け、サービスの品質を維持し、コミュニティ全体の健全性を保つことができます。',
            new Source(
                self::DEFAULT_SOURCE_TITLE,
                self::DEFAULT_SOURCE_URL
            ),
            9999
        )];
    }

    /**
     * @return array{ topic:Topic, created_at:string, edited_at:string }|false
     */
    function getSingleTopic(int $id, int $totalTime): array|false
    {
        $dbDto = $this->model->getQuizApiQuestionDtoById($id);
        if (!$dbDto)
            return false;

        $questions = $this->buildQuestions([$dbDto]);

        $topic = match (ExamType::from($dbDto->type)) {
            ExamType::Bronze => 'ブロンズ',
            ExamType::Silver => 'シルバー',
            ExamType::Gold => 'ゴールド',
        };

        return [
            'topic' => new Topic(
                $topic,
                1,
                1,
                $totalTime,
                $questions
            ),
            'created_at' => $dbDto->created_at,
            'edited_at' => $dbDto->edited_at,
        ];
    }

    function getTopic(ExamType $type, int $lengh, int $totalTime): Topic|false
    {
        $dbDtos = $this->model->getQuizApiQuestionDto($type, $lengh);
        if ($dbDtos) {
            $questions = $this->buildQuestions($dbDtos);
        } elseif (!$dbDtos && $type === ExamType::Bronze) {
            $questions = $this->buildSampleQuestions();
        } else {
            return false;
        }

        $count = count($questions);

        $topic = match ($type) {
            ExamType::Bronze => 'ブロンズ',
            ExamType::Silver => 'シルバー',
            ExamType::Gold => 'ゴールド',
        };

        return new Topic(
            $topic,
            $count,
            $count,
            $totalTime,
            $questions
        );
    }
}
