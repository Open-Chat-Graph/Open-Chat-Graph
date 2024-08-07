<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Config\AdminConfig;
use App\Models\Accreditation\AccreditationUserModel;
use App\Services\Accreditation\AccreditationUtility;
use App\Services\Accreditation\Auth\CookieLineUserLogin;
use App\Services\Accreditation\Enum\ExamType;
use App\Services\Accreditation\QuizOgpGenerator;
use App\Services\OpenChat\Crawler\OpenChatCrawler;
use Shared\Exceptions\UnauthorizedException;
use Shared\Exceptions\ValidationException;

class AccreditationPostApiController
{
    function registerProfile(
        AccreditationUserModel $model,
        OpenChatCrawler $openChatCrawler,
        CookieLineUserLogin $login,
        string $name,
        string $url,
        string $admin_key,
        string $return_to
    ) {
        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);

        if ($admin_key && $admin_key === AdminConfig::ADMIN_ACCREDITATION_KEY)
            $is_admin = 1;
        elseif ($admin_key)
            throw new ValidationException('不正なパスワードです');
        else
            $is_admin = ($profile && $profile['is_admin']) ? 1 : 0;

        if ($url) {
            $dto = $openChatCrawler->fetchOpenChatDto(
                $openChatCrawler->parseInvitationTicketFromUrl($url)
            );

            if (!$dto)
                throw new ValidationException('無効なURLが入力されました。ページを戻って再度入力しなおしてください。');

            $room_name = $dto->name;
        } else {
            $room_name = '';
        }

        $result = $model->registerProfile(
            compact('user_id', 'name', 'url', 'room_name', 'is_admin'),
            getIP(),
            getUA(),
        );

        return redirect($return_to, 303);
    }

    private function buildExplanationArray(
        string $explanation,
        string $source_url,
    ): array {
        if ($source_url === '') {
            $source_title = '';
            return compact('explanation', 'source_url', 'source_title');
        }

        $pageTitle = AccreditationUtility::getPageTitle($source_url);
        if ($pageTitle['error']) {
            throw new ValidationException("出典URLに無効なURLが入力されました。ページを戻って再度入力しなおしてください。\n" . $pageTitle['message']);
        }

        $source_title = $pageTitle['title'];
        return compact('explanation', 'source_url', 'source_title');
    }

    function registerQuestion(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        string $question,
        array $answers,
        string $explanation,
        string $source_url,
        string $type,
        string $return_to,
    ) {
        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $user_id = $model->getUserIncrementId($user_id);
        if (!$user_id)
            throw new UnauthorizedException('プロフィール未作成');

        $publishing = 0;
        $type = ExamType::from($type);

        $explanation = $this->buildExplanationArray($explanation, $source_url);

        $model->registerQuestion(
            compact('question', 'answers', 'explanation', 'user_id', 'publishing', 'type'),
            getIP(),
            getUA(),
        );

        return redirect($return_to, 303);
    }

    function editQuestion(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        QuizOgpGenerator $quizOgpGenerator,
        string $question,
        array $answers,
        string $explanation,
        string $source_url,
        string $type,
        int $id,
        int $publishing,
        string $return_to,
    ) {
        $type = ExamType::from($type);

        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);
        if (!$profile)
            throw new UnauthorizedException('プロフィール未作成');

        $q = $model->getQuestionById($id);
        if (!$q)
            throw new ValidationException('存在しない問題のID');

        $edit_user_id = $profile['id'];
        if (!AccreditationUtility::isQuestionEditable($q, $edit_user_id, !!$profile['is_admin']))
            throw new UnauthorizedException('編集権限がありません');

        if ($publishing && !$profile['is_admin'])
            throw new UnauthorizedException('公開設定権限がありません');

        $explanation = $this->buildExplanationArray($explanation, $source_url);

        $model->updateQuestion(
            compact('id', 'question', 'answers', 'explanation', 'edit_user_id', 'publishing', 'type'),
            getIP(),
            getUA(),
        );

        if ($publishing && (!$q->publishing || $q->question !== $question)) {
            $quizOgpGenerator->generateTextOgp(
                $question,
                'quiz_img_' . $id,
            );
        }

        return redirect($return_to, 303);
    }

    function deleteQuestion(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        int $id,
        string $return_to,
    ) {
        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);
        if (!$profile)
            throw new UnauthorizedException('プロフィール未作成');

        $q = $model->getQuestionById($id);
        if (!$q)
            throw new ValidationException('存在しない問題のID');

        $edit_user_id = $profile['id'];
        if (!AccreditationUtility::isQuestionEditable($q, $edit_user_id, !!$profile['is_admin']))
            throw new UnauthorizedException('編集権限がありません');

        $model->deleteQuestion(
            $id,
            $edit_user_id,
            getIP(),
            getUA(),
        );

        return redirect($return_to, 303);
    }

    function resetPermissionQuestion(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        int $id,
        string $return_to,
    ) {
        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);
        if (!$profile)
            throw new UnauthorizedException('プロフィール未作成');

        $q = $model->getQuestionById($id);
        if (!$q)
            throw new ValidationException('存在しない問題のID');

        if (!$profile['is_admin'])
            throw new UnauthorizedException('設定権限がありません');

        $model->changePermissionQuestion(
            $id,
            $q->user_id,
            $profile['id'],
            getIP(),
            getUA(),
        );

        return redirect($return_to, 303);
    }

    function moveQuestion(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        int $id,
        string $type,
    ) {
        $type = ExamType::from($type);

        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);
        if (!$profile)
            throw new UnauthorizedException('プロフィール未作成');

        $q = $model->getQuestionById($id);
        if (!$q)
            throw new ValidationException('存在しない問題のID');

        if (!$profile['is_admin'])
            throw new UnauthorizedException('設定権限がありません');

        $model->moveQuestion(
            $id,
            $type,
            $profile['id'],
            getIP(),
            getUA(),
        );

        return redirect("/accreditation/{$type->value}/editor?id={$id}", 303);
    }

    function setAdminPermission(
        AccreditationUserModel $model,
        CookieLineUserLogin $login,
        int $id,
        int $is_admin,
        string $return_to,
    ) {
        $user_id = $login->login();
        if (!$user_id)
            throw new UnauthorizedException('未ログイン');

        $profile = $model->getProfile($user_id);
        if (!$profile)
            throw new UnauthorizedException('プロフィール未作成');

        if (!in_array($profile['id'], AdminConfig::ACCREDITATION_TRUE_ADMIN_USER_ID))
            throw new UnauthorizedException('設定権限がありません');

        $model->setAdminPermission(
            $id,
            $is_admin,
            $profile['id'],
            getIP(),
            getUA(),
        );

        return redirect($return_to, 303);
    }
}
