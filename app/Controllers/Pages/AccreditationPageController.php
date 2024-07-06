<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\Accreditation\AccreditationUserModel;
use App\Services\Accreditation\Enum\ExamType;
use App\Services\Accreditation\QuizApi\Dto\Topic;
use App\Services\Accreditation\QuizApi\QuizApiService;
use App\Services\Bot\BotFightUserAgents;
use DateTime;

class AccreditationPageController
{
    private const SINGLE_TIME = 45;

    function privacy()
    {
        return view('accreditation/privacy');
    }

    function index(QuizApiService $quizApiService, int $id)
    {
        $_css = getFilePath('style/quiz', 'main.*.css');
        $_js = getFilePath('js/quiz', 'main.*.js');

        if ($id)
            return $this->singleQuiz($quizApiService, $id, $_css, $_js);

        $_argDto = $quizApiService->getTopic(ExamType::Bronze, 10, 300);
        $_argDto_silver = $quizApiService->getTopic(ExamType::Silver, 20, 600);
        $_argDto_gold = $quizApiService->getTopic(ExamType::Gold, 30, 900);

        $title = 'オプチャ検定｜公式サイト';
        $description = 'オプチャ検定は、ガイドラインやルール、管理方法などについての知識を深める場所です。LINEオープンチャットを利用する際に必要な情報を楽しく学ぶことができます。';
        $ogp = fileUrl("assets/quiz-ogp.png");
        $canonical = url("accreditation");

        return view(
            'accreditation/quiz',
            compact(
                '_argDto',
                '_argDto_silver',
                '_argDto_gold',
                '_css',
                '_js',
                'title',
                'description',
                'ogp',
                'canonical',
            )
        );
    }

    private function singleQuiz(QuizApiService $quizApiService, int $id, string $_css, string $_js)
    {
        $topic = $quizApiService->getSingleTopic($id, self::SINGLE_TIME);
        if (!$topic)
            return false;

        ['topic' => $_argDto, 'created_at' => $created_at, 'edited_at' => $edited_at] = $topic;

        $description = $_argDto->questions[0]->question
            . ' A.' . $_argDto->questions[0]->choices[0]
            . ' B.' . $_argDto->questions[0]->choices[1]
            . ' C.' . $_argDto->questions[0]->choices[2]
            . ' D.' . $_argDto->questions[0]->choices[3];

        $title = "{$_argDto->questions[0]->question}｜オプチャ検定｜Q.{$id}";
        $ogp = fileUrl("quiz-img/quiz_img_{$id}.webp");
        $canonical = url("accreditation?id={$id}");

        /** @var BotFightUserAgents $bot */
        $bot = app(BotFightUserAgents::class);
        $isCrawler = $bot->isCrawler(getUA());

        return view(
            'accreditation/quiz',
            compact(
                '_argDto',
                '_css',
                '_js',
                'title',
                'description',
                'ogp',
                'canonical',
                'created_at',
                'edited_at',
                'isCrawler',
            )
        );
    }

    function today(
        QuizApiService $quizApiService,
        AccreditationUserModel $accreditationUserModel,
    ) {
        $_css = getFilePath('style/quiz', 'main.*.css');
        $_js = getFilePath('js/quiz', 'main.*.js');

        $fileName = 'accreditation/today_question.dat';
        $date = (new DateTime())->format('Y-m-d');

        $question = getUnserializedFile($fileName);
        if (!$question || $question['date'] !== $date) {
            $ids = $accreditationUserModel->getQuestionIds();
            if (!$ids)
                return false;

            $topic = $quizApiService->getSingleTopic($ids[array_rand($ids)], self::SINGLE_TIME);
            if (!$topic)
                return false;

            ['topic' => $_argDto, 'created_at' => $created_at, 'edited_at' => $edited_at] = $topic;

            saveSerializedFile('accreditation/today_question.dat', compact('date', '_argDto'));
        } else {
            /** @var Topic */
            $_argDto = $question['_argDto'];
        }

        $title = '今日の一問｜オプチャ検定';
        $description = 'オプチャ検定は、ガイドラインやルール、管理方法などについての知識を深める場所です。LINEオープンチャットを利用する際に必要な情報を楽しく学ぶことができます。';
        $ogp = fileUrl("assets/quiz-today.png");
        $canonical = url("accreditation/today");

        $cookie = cookie('accreditation-today');
        cookie(['accreditation-today' => $date], time() + 60 * 60 * 24);

        $formatedDate = (new DateTime())->format('n/j');
        $subTitle = $cookie !== $date ? "【今日の１問 {$formatedDate}】 " : "【今日の１問 {$formatedDate} 再挑戦】 ";
        $_argDto->questions[0]->question = $subTitle . $_argDto->questions[0]->question;

        return view(
            'accreditation/quiz',
            compact(
                '_argDto',
                '_css',
                '_js',
                'title',
                'description',
                'ogp',
                'canonical',
            )
        );
    }
}
