<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\Admin\AdminAuthService;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Recommend\TagDefinition\TagMetadata;
use Shadow\Kernel\Reception;
use Shared\Exceptions\NotFoundException;
use Shared\MimimalCmsConfig;

/**
 * おすすめタグ定義(Git管理JSON: data/{lang}.json)を編集する管理者専用GUIのバックエンド。
 *
 * - ja/th/tw 各ロケール対応（編集対象は urlRoot に対応する data/{lang}.json）・管理者専用・ローカル編集用途。
 * - 本番DBには一切触れない（ファイルの読み書きのみ）。
 * - 表示(index)はファイルの生JSONとデコード済み配列をViewへ渡すだけ。
 * - 保存(save)は受け取ったJSONを検証し、一時ファイル経由のrenameで原子的に差し替える。
 *
 * 画面のHTML/JSは別途フロントエンド側がView(admin/recommend_tags_editor.php)として用意する。
 */
class AdminRecommendTagController
{
    /** 保存時に必須となるトップレベルキー（いずれも配列であること） */
    private const REQUIRED_ARRAY_KEYS = [
        'strongest',
        'beforeCategory',
        'subCategoriesTag',
        'nameStrong',
        'descStrong',
        'afterDescStrong',
    ];

    /** キーワード群（各エントリが tag(非空文字列) を持つ配列）であるべきトップレベルキー */
    private const KEYWORD_GROUP_KEYS = [
        'strongest',
        'nameStrong',
        'descStrong',
        'afterDescStrong',
    ];

    /** 保存を許可するトップレベルキー（$_GET 混入や未知キーの混入を防ぐホワイトリスト） */
    private const ALLOWED_TOP_KEYS = [
        'strongest',
        'beforeCategory',
        'subCategoriesTag',
        'nameStrong',
        'descStrong',
        'afterDescStrong',
        'omitPattern',
        'redirects',
        'recommendPageTagFilter',
        'filteredTagSort',
        'topPageTagFilter',
        'descriptions',
    ];

    /** ja.json と同じ整形フラグ（保存時の見た目を一致させる） */
    private const JSON_ENCODE_FLAGS =
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    public function __construct(AdminAuthService $adminAuthService, private BatchScriptLauncher $batchScriptLauncher)
    {
        if (!$adminAuthService->auth()) {
            throw new NotFoundException;
        }
    }

    /**
     * 編集画面の表示。
     *
     * ja.json を読み込み、整形済みの生JSON文字列とデコード済み配列の両方をViewへ渡す。
     * View(admin/recommend_tags_editor.php)はフロントエンド側が用意する。
     */
    public function index()
    {
        $jsonPath = TagMetadata::jsonPath(MimimalCmsConfig::$urlRoot);
        // 画面に表示する「編集対象ファイル」の相対パス（ja.json ハードコードを避けロケール別に出す）
        $lang = MimimalCmsConfig::$urlRoot === '' ? 'ja' : ltrim(MimimalCmsConfig::$urlRoot, '/');
        $jsonRelPath = "app/Services/Recommend/TagDefinition/data/{$lang}.json";

        if (!is_file($jsonPath) || !is_readable($jsonPath)) {
            return view('admin/admin_message_page', [
                'title' => 'おすすめタグ編集',
                'message' => "タグ定義JSONが見つからないか読み取れません。\n{$jsonPath}",
            ]);
        }

        $raw = file_get_contents($jsonPath);
        if ($raw === false) {
            return view('admin/admin_message_page', [
                'title' => 'おすすめタグ編集',
                'message' => "タグ定義JSONの読み込みに失敗しました。\n{$jsonPath}",
            ]);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return view('admin/admin_message_page', [
                'title' => 'おすすめタグ編集',
                'message' => "タグ定義JSONが壊れています（パースできません）。\n{$jsonPath}",
            ]);
        }

        // ファイル上の整形と完全一致させた生JSONを渡す（フロントの初期テキスト用）
        $tagJson = json_encode($decoded, self::JSON_ENCODE_FLAGS);

        $_meta = meta()->setTitle('おすすめタグ編集');

        // 保存POST用のCSRFトークンを発行し画面に埋め込む。保存fetchは X-CSRF-Token ヘッダで
        // これを送る（HttpOnly cookie の自動送信に依存しない確実な方式）。
        // verifyCsrfToken は hash('sha256', token) === $_SESSION['_csrf'] で照合する。
        $csrfToken = bin2hex(random_bytes(16));
        $_SESSION['_csrf'] = hash('sha256', $csrfToken);
        \Shadow\Kernel\Cookie::push(['CSRF-Token' => $csrfToken]);
        $_COOKIE['CSRF-Token'] = $csrfToken;

        // _tagJson / _tagData は View のサニタイズ (htmlspecialchars) をスキップする命名規約
        // (キーが '_' で始まると View::sanitizeArray が素通しする)。これを使わないと
        // 説明文中の "&quot;" や "King&Prince" の '&' が保存→読込のたびに &amp; → &amp;amp; と
        // 多重エスケープされて累積する不具合になる。
        return view('admin/recommend_tags_editor', [
            '_tagJson' => $tagJson,
            '_tagData' => $decoded,
            '_csrfToken' => $csrfToken,
            '_jsonRelPath' => $jsonRelPath,
            '_meta' => $_meta,
        ]);
    }

    /**
     * 全レコードへタグを即時再適用（ja=無停止シャドウ再構築 / th・tw=フル再構築）をバックグラウンドで開始する。
     * ローカルで結果をすぐ確認したいとき、デプロイ後の手動反映に使う。
     * 通常はデプロイ後の毎時CRONが {lang}.json の変更を自動検知して再適用するため、これは任意。
     */
    public function rebuild()
    {
        // urlRoot を渡してロケール別に再構築（'' なら ja）。バックグラウンドで起動。
        // admin/編集画面からの手動反映は最新の編集を確実に反映したいので、実行中の前ランを
        // kill して後発で再実行する（--cancel-previous）。
        $this->batchScriptLauncher->launchInBackground(BatchScript::tagUpdate, (string)MimimalCmsConfig::$urlRoot, '--cancel-previous');
        return response(['ok' => true]);
    }

    /**
     * 編集後のja.json全体を受け取り、検証してファイルへ原子的に保存する。
     *
     * 入力: application/json のリクエストボディ、または form フィールド `json`。
     * 成功: JSON `{"ok": true}` を返す。
     * 失敗: HTTP 4xx/5xx + JSON `{"ok": false, "error": "..."}` を返す。
     */
    public function save()
    {
        // 1) 入力の取り出し（application/json ボディ全体 or form フィールド `json`）
        $payload = $this->resolveInputPayload();
        if ($payload === null) {
            return response(['ok' => false, 'error' => '保存するJSONが空です。'], 400);
        }

        // 2) JSONとして妥当か
        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response(['ok' => false, 'error' => 'JSONとして解析できません: ' . json_last_error_msg()], 400);
        }

        // 2.5) 既知のトップレベルキーだけに絞る（$_GET 混入・未知キーの混入防止）
        if (is_array($decoded)) {
            $decoded = array_intersect_key($decoded, array_flip(self::ALLOWED_TOP_KEYS));
        }

        // 3) 構造の検証
        $error = $this->validateStructure($decoded);
        if ($error !== null) {
            return response(['ok' => false, 'error' => $error], 422);
        }

        // 4) 原子的書き込み（同一ディレクトリへ一時ファイル→rename）
        //    クライアントから来たキー順を保つため、デコード結果をそのまま再エンコードする。
        $encoded = json_encode($decoded, self::JSON_ENCODE_FLAGS);
        if ($encoded === false) {
            return response(['ok' => false, 'error' => 'JSONの再エンコードに失敗しました。'], 500);
        }

        $error = $this->writeAtomically(TagMetadata::jsonPath(MimimalCmsConfig::$urlRoot), $encoded . "\n");
        if ($error !== null) {
            return response(['ok' => false, 'error' => $error], 500);
        }

        return response(['ok' => true]);
    }

    /**
     * 保存対象のJSON文字列を取り出す。
     * application/json ボディなら `Reception::input()` の配列を再エンコード、
     * フォーム送信なら `json` フィールドの文字列を返す。
     */
    private function resolveInputPayload(): ?string
    {
        // フォームフィールド `json`（文字列）で送られたケース
        $jsonField = Reception::input('json');
        if (is_string($jsonField) && $jsonField !== '') {
            return $jsonField;
        }

        // application/json ボディで送られたケース（フレームワークが配列化済み）
        $all = Reception::input();
        if (is_array($all) && $all !== []) {
            $encoded = json_encode($all, self::JSON_ENCODE_FLAGS);
            if (is_string($encoded)) {
                return $encoded;
            }
        }

        return null;
    }

    /**
     * 保存前の構造検証。問題があればエラーメッセージ、無ければ null を返す。
     *
     * @param mixed $decoded json_decode 済みの値
     */
    private function validateStructure(mixed $decoded): ?string
    {
        // (b) 連想配列であること
        if (!is_array($decoded) || array_is_list($decoded)) {
            return 'トップレベルはオブジェクト（連想配列）である必要があります。';
        }

        // (c) 必須トップレベルキーが存在し配列であること
        foreach (self::REQUIRED_ARRAY_KEYS as $key) {
            if (!array_key_exists($key, $decoded)) {
                return "必須キー \"{$key}\" がありません。";
            }
            if (!is_array($decoded[$key])) {
                return "キー \"{$key}\" は配列である必要があります。";
            }
        }

        // (e) キーワード群（リスト）の各エントリが非空文字列の tag を持つこと
        foreach (self::KEYWORD_GROUP_KEYS as $key) {
            $error = $this->validateEntryList($decoded[$key], $key);
            if ($error !== null) {
                return $error;
            }
        }

        // beforeCategory は「カテゴリ文字列キー → エントリ配列」のマップ
        foreach ($decoded['beforeCategory'] as $category => $entries) {
            if (!is_array($entries)) {
                return "beforeCategory[\"{$category}\"] は配列である必要があります。";
            }
            $error = $this->validateEntryList($entries, "beforeCategory[\"{$category}\"]");
            if ($error !== null) {
                return $error;
            }
        }

        // subCategoriesTag も「カテゴリ文字列キー → エントリ配列」のマップ
        foreach ($decoded['subCategoriesTag'] as $category => $entries) {
            if (!is_array($entries)) {
                return "subCategoriesTag[\"{$category}\"] は配列である必要があります。";
            }
            $error = $this->validateEntryList($entries, "subCategoriesTag[\"{$category}\"]");
            if ($error !== null) {
                return $error;
            }
        }

        // (d) redirects があれば old !== new
        if (array_key_exists('redirects', $decoded)) {
            if (!is_array($decoded['redirects'])) {
                return 'キー "redirects" は配列である必要があります。';
            }
            foreach ($decoded['redirects'] as $old => $new) {
                if ((string)$old === (string)$new) {
                    return "redirects: 旧ラベルと新ラベルが同一です（\"{$old}\"）。";
                }
            }

            // 現役タグ（キーワード群に存在するラベル）をリダイレクト元にはできない
            // （その一覧ページが常に301転送されて表示不能になるため）
            $liveLabels = $this->collectLiveLabels($decoded);
            foreach ($decoded['redirects'] as $old => $new) {
                if (isset($liveLabels[(string)$old])) {
                    return "redirects: 旧ラベル \"{$old}\" は現役タグのためリダイレクト元にできません。";
                }
            }
        }

        return null;
    }

    /**
     * キーワード群（strongest/nameStrong/descStrong/afterDescStrong と beforeCategory 配下）に
     * 存在する現役タグラベルの集合を返す（ラベル => true）。
     *
     * @param array<string,mixed> $decoded
     * @return array<string,true>
     */
    private function collectLiveLabels(array $decoded): array
    {
        $labels = [];
        foreach (self::KEYWORD_GROUP_KEYS as $key) {
            foreach ((array)($decoded[$key] ?? []) as $entry) {
                if (is_array($entry) && isset($entry['tag']) && is_string($entry['tag'])) {
                    $labels[$entry['tag']] = true;
                }
            }
        }
        foreach ((array)($decoded['beforeCategory'] ?? []) as $entries) {
            foreach ((array)$entries as $entry) {
                if (is_array($entry) && isset($entry['tag']) && is_string($entry['tag'])) {
                    $labels[$entry['tag']] = true;
                }
            }
        }
        foreach ((array)($decoded['subCategoriesTag'] ?? []) as $entries) {
            foreach ((array)$entries as $entry) {
                if (is_array($entry) && isset($entry['tag']) && is_string($entry['tag'])) {
                    $labels[$entry['tag']] = true;
                }
            }
        }
        return $labels;
    }

    /**
     * キーワード群エントリ配列の検証。各要素は tag(非空文字列) を持つ必要がある。
     *
     * @param mixed  $list  検証対象（配列であること期待）
     * @param string $label エラーメッセージ用の場所名
     */
    private function validateEntryList(mixed $list, string $label): ?string
    {
        if (!is_array($list)) {
            return "{$label} は配列である必要があります。";
        }

        foreach ($list as $i => $entry) {
            if (!is_array($entry)) {
                return "{$label}[{$i}] はオブジェクトである必要があります。";
            }
            $tag = $entry['tag'] ?? null;
            if (!is_string($tag) || $tag === '') {
                return "{$label}[{$i}] の \"tag\" は非空の文字列である必要があります。";
            }
        }

        return null;
    }

    /**
     * 同一ディレクトリの一時ファイルへ書き込み、rename() で原子的に差し替える。
     * 失敗時は分かりやすい権限エラーメッセージを返し、成功時は null を返す。
     */
    private function writeAtomically(string $path, string $contents): ?string
    {
        $dir = dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            return "保存先ディレクトリに書き込めません（権限を確認してください）: {$dir}";
        }
        if (is_file($path) && !is_writable($path)) {
            return "ファイルに書き込めません（権限を確認してください）: {$path}";
        }

        $tmpPath = $path . '.tmp.' . getmypid();

        if (file_put_contents($tmpPath, $contents, LOCK_EX) === false) {
            return "一時ファイルの書き込みに失敗しました（権限を確認してください）: {$tmpPath}";
        }

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            return "ファイルの差し替えに失敗しました（権限を確認してください）: {$path}";
        }

        return null;
    }
}
