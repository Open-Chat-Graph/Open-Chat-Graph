<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Models\Accreditation\AccreditationHomePageModel;
use App\Models\Accreditation\AccreditationUserModel;
use App\Services\Accreditation\AccreditationUtility;
use App\Services\Accreditation\Auth\CookieLineUserLogin;
use App\Services\Accreditation\Dto\QuestionDto;
use App\Services\Accreditation\Enum\ExamType;
use Shadow\Kernel\Reception;
use Shared\Exceptions\NotFoundException;

class AccreditationController
{
    /** 
     * @var array{ id:int,user_id:string,name:string,url:string,room_name:string,is_admin:int,created_at:string }|false
     */
    public array|false $profileArray = false;
    public int $myId = 0;
    public bool $isAdmin = false;

    /** 
     * @var array{ id:int,user_id:string,name:string,url:string,room_name:string,is_admin:int,created_at:string }|false
     */
    public array|false $currentProfileArray = false;
    public int $currentPage = 1;
    public int $currentId = 0;

    /** 
     * @var array{ id:int,user_id:string,name:string,url:string,room_name:string,is_admin:int,created_at:string }[]
     */
    public array $currentContributorsArray = [];

    public ExamType $type;
    public string $pageType;
    private AccreditationUserModel $accreditationUserModel;

    /**
     * @var QuestionDto[]
     */
    public array $questionList = [];

    function homeLogin(CookieLineUserLogin $login)
    {
        $userId = $login->login();
        if ($userId)
            return redirect("accreditation/bronze/home", 307);

        return view('accreditation/home_login');
    }

    function route(
        CookieLineUserLogin $login,
        AccreditationUserModel $accreditationUserModel,
        string $examType,
        string $pageType,
        int $page,
        int $id,
    ) {
        $type = ExamType::tryFrom($examType);
        if (!$type || $pageType === 'homelogin' || !method_exists($this, $pageType))
            throw new NotFoundException;

        $this->type = $type;
        $this->pageType = $pageType;
        $this->accreditationUserModel = $accreditationUserModel;

        $userId = $login->login();
        if (!$userId && $pageType !== 'login')
            return redirect("accreditation/{$examType}/login", 307);
        elseif ($userId && $pageType === 'login')
            return redirect("accreditation/{$examType}/home", 307);

        if ($userId) {
            $this->profileArray = $accreditationUserModel->getProfile($userId);
            if (!$this->profileArray && $pageType !== 'profile')
                return redirect("accreditation/{$examType}/profile", 307);

            if ($this->profileArray) {
                $this->myId = $this->profileArray['id'];
                $this->isAdmin = !!$this->profileArray['is_admin'];
            }
        }

        $this->currentId = $id;
        $this->currentPage = $page;

        return $this->$pageType(['controller' => $this]);
    }

    function home(array $controller)
    {
        $model = app(AccreditationHomePageModel::class);

        return view('accreditation/home', $controller + compact('model'));
    }

    function profile(array $controller)
    {
        return view('accreditation/profile', $controller);
    }

    function user(array $controller)
    {
        $this->questionList = $this->accreditationUserModel->getMyQuestionList(
            $this->currentId,
            Reception::has('all') ? null : $this->type
        );

        if ($this->profileArray['id'] === $this->currentId) {
            $this->currentProfileArray = $this->profileArray;
        } else {
            $this->currentProfileArray = $this->accreditationUserModel->getProfileById($this->currentId);
            if (!$this->currentProfileArray)
                return false;
        }

        return view('accreditation/user', $controller);
    }

    function login(array $controller)
    {
        return view('accreditation/login', $controller);
    }

    function editor(array $controller)
    {
        $q = $this->accreditationUserModel->getQuestionById($this->currentId, $this->type);
        if (!$q)
            return false;

        if (!AccreditationUtility::isQuestionEditable($q, $this->myId, $this->isAdmin))
            return false;

        $this->questionList = [$q];

        return view('accreditation/editor', $controller);
    }

    function question(array $controller)
    {
        return view('accreditation/question', $controller);
    }

    function unpublished(array $controller)
    {
        $this->questionList = Reception::has('all')
            ? $this->accreditationUserModel->getQuestionListAll(0)
            : $this->accreditationUserModel->getQuestionList(0, $this->type);

        return view('accreditation/questionList', $controller);
    }

    function published(array $controller)
    {
        $this->questionList = Reception::has('all')
            ? $this->accreditationUserModel->getQuestionListAll(1)
            : $this->accreditationUserModel->getQuestionList(1, $this->type);

        return view('accreditation/questionList', $controller);
    }

    function contributors(array $controller)
    {
        $this->currentContributorsArray = $this->accreditationUserModel->getProfilesByType(
            Reception::has('all') ? null : $this->type
        );

        return view('accreditation/contributors', $controller);
    }

    function member(array $controller)
    {
        $this->currentContributorsArray = $this->accreditationUserModel->getProfilesAll($this->type);

        return view('accreditation/member', $controller);
    }
}
