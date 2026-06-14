<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Config\AppConfig;
use App\Services\Recommend\RecommendPageList;
use App\Services\Recommend\ThemeDiscoveryService;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagDescription;
use App\Services\Recommend\TagDefinition\Ja\RecommendTagFilters;
use App\Services\Recommend\TagDefinition\Ja\RecommendUtility;
use App\Services\Recommend\TagDefinition\JsonRecommendUpdaterTags;
use App\Services\StaticData\StaticDataFile;
use App\Views\Schema\PageBreadcrumbsListSchema;
use Shared\MimimalCmsConfig;

class RecommendOpenChatPageController
{
    function __construct(
        private PageBreadcrumbsListSchema $breadcrumbsShema,
    ) {}

    /**
     * リダイレクト表を大文字小文字を無視して引く。
     *
     * ルータ(shadow/Kernel/Dispatcher/RequestParser)が URI を strtolower 済みのため、
     * URL の $tag は常に小文字で渡る。一方 redirects のキーは元の表記を保つので、
     * 大文字を含むキー(例: BNK / Pokemon / Signal / GMM / ChatGPT / NBA)が
     * case-sensitive な isset() でヒットせず、301 ではなく 410 を返していた。
     * getValidTag() と同じく case-insensitive で照合し、旧URLのSEO資産を取りこぼさない。
     *
     * @param array<string,string> $redirects
     * @return string|null 転送先タグ。該当なしは null。
     */
    private static function lookupRedirect(array $redirects, string $tag): ?string
    {
        if (isset($redirects[$tag]))
            return $redirects[$tag];

        $lowerTag = strtolower($tag);
        foreach ($redirects as $key => $value) {
            if (strtolower($key) === $lowerTag)
                return $value;
        }
        return null;
    }

    function index(
        RecommendPageList $recommendPageList,
        StaticDataFile $staticDataGeneration,
        ThemeDiscoveryService $themeDiscoveryService,
        string $tag
    ) {
        AppConfig::$listLimitTopRanking = 5;
        if (MimimalCmsConfig::$urlRoot === '') {
            $redirectTags = RecommendTagFilters::redirectTags();
            $redirectTo = self::lookupRedirect($redirectTags, $tag);
            if ($redirectTo !== null)
                return redirect('recommend/' . urlencode($redirectTo), 301);

            $extractTag = RecommendUtility::getValidTag($tag);
        } else {
            // th/tw: タグ定義刷新で改称した旧サブカテゴリ由来タグ({lang}.jsonのredirects)を
            // 301で新タグへ引き継ぎ、既存のGoogleランキング/被リンク資産を保全する。
            $redirects = JsonRecommendUpdaterTags::forLocale(MimimalCmsConfig::$urlRoot)->getMetadata('redirects');
            $redirectTo = self::lookupRedirect($redirects, $tag);
            if ($redirectTo !== null)
                return redirect(url('recommend/' . urlencode($redirectTo)), 301);
            $extractTag = $tag;
        }

        $tag = $recommendPageList->getValidTag($tag);
        if (!$tag) {
            // th/tw: 刷新で廃止した旧タグページは 404 でなく 410 Gone を返す。
            // 「恒久的に削除された」ことを Google に明示し、再クロールキューから速やかに外す。
            // （移行で消える旧タグのうち受け皿があるものは上の 301 で引き継ぎ済み）
            if (MimimalCmsConfig::$urlRoot !== '') {
                http_response_code(410);
                return view('errors/error', ['httpCode' => 410]);
            }
            return false;
        }

        $extractTag = $extractTag ?: $tag;

        // 高需要タグ向けのテーマ固有紹介文。ja は ja.json、th/tw は {lang}.json の
        // descriptions セクションから引く（日本版と同等にテーマ紹介文を表示）。無ければ null。
        $tagDescription = MimimalCmsConfig::$urlRoot === ''
            ? RecommendTagDescription::get($tag)
            : (JsonRecommendUpdaterTags::forLocale(MimimalCmsConfig::$urlRoot)->getMetadata('descriptions')[$tag] ?? null);

        $_dto = $staticDataGeneration->getRecommendPageDto();

        $count = 0;

        if (MimimalCmsConfig::$urlRoot === '') {
            $pageDesc =
                "「{$tag}」のLINEオープンチャット(オプチャ)を、いま活発に伸びている部屋順に一覧化。メンバー数の増減を1時間ごとに集計してランキング化しているので、今まさに参加者が増えている部屋・新しく募集中の部屋がひと目で分かります。新着ルームも随時追加。";
        } elseif (MimimalCmsConfig::$urlRoot === '/tw') {
            $pageDesc =
                "將「{$tag}」的 LINE 社群(OpenChat)依正在活躍成長的順序整理。我們每小時統計成員人數的增減並排名，讓你一眼看出此刻人數正在增加、正在招募新成員的社群。也會隨時新增新的社群。";
        } elseif (MimimalCmsConfig::$urlRoot === '/th') {
            $pageDesc =
                "รวม LINE OpenChat หัวข้อ \"{$tag}\" โดยเรียงตามห้องที่กำลังเติบโตและคึกคัก เรานับการเปลี่ยนแปลงจำนวนสมาชิกทุกชั่วโมงเพื่อจัดอันดับ คุณจึงเห็นได้ทันทีว่าห้องไหนกำลังมีคนเพิ่มขึ้นหรือกำลังรับสมาชิกใหม่ และมีการเพิ่มห้องใหม่อยู่เสมอ";
        }

        $_meta = meta()
            ->setDescription($pageDesc)
            ->setOgpDescription($pageDesc);

        $_css = ['components/room_list', 'components/site_header', 'components/site_footer', 'components/theme_discovery', 'pages/recommend_page'];

        $_breadcrumbsShema = $this->breadcrumbsShema->generateSchema(
            $extractTag,
        );

        $canonical = url('recommend/' . urlencode($tag));

        $topPageDto = $staticDataGeneration->getTopPageData();

        $recommend = $recommendPageList->getListDto($tag);

        // テーマ発見セクション（/recommend 着地客の回遊導線）。表示ロジックは Service が確定し DTO を返す。
        // View へは `_discovery` で渡し、フレームワークの自動エスケープを通さない（View 側で明示エスケープ）。
        // 関連タグは .dat に同梱済みの自タグ分（毎時バッチが related_tags マップと同時刻に生成）。
        // 未同梱（移行期の旧 .dat・ライブ生成）は空表示とし、次の毎時生成で自然に埋まる。
        $relatedTagsForTag = $recommend ? ($recommend->relatedTags ?? []) : [];
        $_discovery = $themeDiscoveryService
            ->build(
                $staticDataGeneration->getTagList(),
                $tag,
                $topPageDto,
                $relatedTagsForTag,
            );
        if (!$recommend || !$recommend->getCount()) {
            $_schema = '';
            $_meta->setTitle(sprintfT('「%s」のオープンチャット｜人気・活発な部屋ランキング', $tag));
            noStore();
            // topPageDto は ThemeDiscoveryService への入力のみで recommend_content では未使用のため
            // view へは渡さない (ビューの再帰エスケープの無駄を避ける)
            return view('recommend_content', compact(
                '_meta',
                '_css',
                'tag',
                'extractTag',
                '_breadcrumbsShema',
                'count',
                '_schema',
                '_dto',
                '_discovery',
                'canonical',
                'tagDescription',
            ));
        }

        $recommendList = $recommend->getList(false);
        $hourlyUpdatedAt = new \DateTime($recommend->hourlyUpdatedAt);

        $count = min($recommend->getCount(), AppConfig::LIST_LIMIT_RECOMMEND);
        $headline = sprintfT('「%s」のオープンチャット｜人気・活発な部屋ランキング', $tag);
        $_meta->setTitle($headline);
        $_meta->setImageUrl(imgUrl($recommendList[0]['img_url']));
        $_meta->thumbnail = imgPreviewUrl($recommendList[0]['img_url']);

        $_schema = $this->breadcrumbsShema->generateRecommend(
            $headline,
            $_meta->description,
            $hourlyUpdatedAt,
            $tag
        );

        // テーマの勢い: 毎時バッチが .dat 生成時に事前計算して DTO に同梱している。
        // .dat が無い場合のライブ集計フォールバックも静的データ層(getRecomendRanking)が保証する
        // ため、コントローラでは同梱値を使うだけ（アクセスごとの SQLite 集計はしない）。
        $growth = $recommend->themeMomentum ?? [];

        // ビュー(recommend_content)が使うのは mergedElements(表示30件) とスカラーのみ。
        // 母集団(最大300件)の $list を空にして、view() の再帰エスケープが
        // 表示されない数百行を毎回走査するのを避ける。
        // このDTOはリクエスト内メモ共有だが、以降このリクエストで母集団を使う処理は無い。
        $recommend->list = [];
        $recommend->shuffledMergedElements = null;

        return view('recommend_content', compact(
            '_meta',
            '_css',
            '_breadcrumbsShema',
            'recommend',
            'tag',
            'extractTag',
            'count',
            '_schema',
            '_dto',
            '_discovery',
            'canonical',
            'hourlyUpdatedAt',
            'tagDescription',
            'growth',
        ));
    }
}
