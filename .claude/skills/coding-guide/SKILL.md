---
name: coding-guide
description: 実装ガイド。新規ページ追加（ルーティング/コントローラ/ビュー）、DB アクセスパターン、スキーマ変更、DI/ServiceProvider、クローラ設定、フロントエンド（React）のビルドと翻訳、ページ系キャッシュ生成アーキテクチャと genetop の詳細。PHP・フロントのコードを書く/変更する前に読む。
---

# 実装ガイド

> 前提: CLAUDE.md の必守ルール（MimimalCMS 本体改造禁止・Repository は Interface とセット・スキーマ変更は schema SQL 編集だけ・キャッシュ生成の genetop/ドキュメント同期）に従う。本ガイドはその詳細と実装手順。

## アーキテクチャ

- **Framework**: 自作 MimimalCMS（軽量 MVC・DI）
- **Language**: PHP 8.5
- **Database**: 主データは MySQL/MariaDB、ランキング・統計は SQLite。生 SQL（ORM 無し）
- **Frontend**: サーバサイド PHP テンプレート＋React 埋め込み（TypeScript, MUI, Chart.js, Swiper.js）

主要ディレクトリ:

- `/app/` - アプリ本体（MVC）
  - `Config/` - ルーティング・アプリ設定
  - `Controllers/` - HTTP ハンドラ（Api/ と Pages/）
  - `Models/` - データアクセス層（Repository）
  - `Services/` - ビジネスロジック
    - `Crawler/Config/` - クローラ設定（OpenChatCrawlerConfig）
  - `ServiceProvider/` - DI 用サービスプロバイダ
  - `Views/` - テンプレートと React コンポーネント
- `/shadow/` - MimimalCMS フレームワーク本体（改造禁止）
- `/batch/` - バックグラウンド処理・cron ジョブ
- `/shared/` - フレームワーク設定・DI マッピング
- `/storage/` - 多言語データファイル・SQLite データベース

設定ファイル:

- 環境固有の設定は `local-secrets.php`（gitignore 済・DB 接続情報もここ）
- フレームワーク設定は `/shared/MimimalCMS_*.php`
- Autoload (PSR-4): `Shadow\` → `shadow/`、`App\` → `app/`、`Shared\` → `shared/`、`App\Views\` → `app/Views/Classes`
- ヘルパー関数の定義場所: `meta()` / `t()` / `getFilePath()` は `app/Helpers/functions.php`、`view()` / `url()` / `fileUrl()` は `shared/MimimalCMS_HelperFunctions.php`（本体側・改造禁止）

## DB アクセス

```php
use Shadow\DB;

DB::connect(); // 必ず最初に接続する

// 複数行 SELECT
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE condition = ?");
$stmt->execute([$value]);
$results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

// 1行 SELECT
$stmt = DB::$pdo->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(\PDO::FETCH_ASSOC);
```

SQLite はランキング・統計データの読み取り最適化用で、データ種別ごとに `/storage/` に分かれている。

### スキーマ変更（テーブル・カラム追加）

`setup/schema/mysql/*.sql` を編集するだけ。デプロイ時に `batch/exec/sync_mysql_schema.php` が各 DB へ
不足分を「追加だけ」自動反映する（既存データは壊さない。削除・型変更はしない）。`deploy.yml` もコードも触らない。

- 反映前に確認: `docker compose exec app php batch/exec/sync_mysql_schema.php --dry-run`
- 詳細・注意点: [`app/Services/Schema/README.md`](../../../app/Services/Schema/README.md)

## DI（Dependency Injection）

- Interface ベースの DI を `/shared/MimimalCmsConfig.php` で設定
- 動的なバインドは `/app/ServiceProvider/` のサービスプロバイダで行う
- 例: `OpenChatCrawlerConfigServiceProvider` は `AppConfig::$isMockEnvironment` に応じて本番/Mock 設定を切り替える

Repository を新規作成するときは必ず `XxxRepositoryInterface` を作り DI バインドする（CLAUDE.md 必守ルール）。
読み取り経路を別所（例: 既存クエリへの JOIN）に寄せて Repository を書き込み専用に縮められる場合もあるが、
その判断とは無関係に Interface は最初から作る。

### MimimalCMS のアプリ側拡張点

横断的な振る舞いは本体（`shadow/`）ではなくアプリ側の拡張点で実現する:

- 例外→HTTP コード対応: `MimimalCmsConfig::$httpErrors` ＋ `app/Exceptions/`
- アプリ固有の例外ハンドリング: `app/Exceptions/Handlers/ApplicationExceptionHandler`（`$exceptionMap`）
- DI 差し替え: `app/ServiceProvider/`
- DB 接続の解放など: app 側の Repository にメソッドを生やしてコントローラから呼ぶ

## 新規ページ追加（MVC）

### 1. ルート追加 — `/app/Config/routing.php`

```php
Route::path('your-path', [\App\Controllers\Pages\YourController::class, 'method']);
```

### 2. コントローラ作成 — `/app/Controllers/Pages/`

```php
<?php
declare(strict_types=1);

namespace App\Controllers\Pages;

use Shadow\Kernel\Reception;
use App\Models\Repositories\DB;

class YourController
{
    public function index(Reception $reception)
    {
        DB::connect();
        $stmt = DB::$pdo->prepare("SELECT ...");
        $stmt->execute();
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $_meta = meta();
        $_meta->title = 'Page Title';

        return view('view_name', ['data' => $data, '_meta' => $_meta]);
    }
}
```

### 3. ビュー作成 — `/app/Views/`

- 拡張子は `.php`
- 変数に直接アクセス: `$data`, `$_meta`
- ヘルパー関数: `url()`, `t()`, `fileUrl()`

## クローリングシステム

- `OpenChatCrawlerConfig` - 本番環境（実際の LINE URL を使用）
- `MockOpenChatCrawlerConfig` - Mock 環境（`line-mock-api` サービスを使用）
- サービスプロバイダが `AppConfig::$isMockEnvironment` に応じて自動で切り替える

User Agent:

```
Mozilla/5.0 (Linux; Android 11; Pixel 5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36 (compatible; OpenChatStatsbot; +https://github.com/Open-Chat-Graph/Open-Chat-Graph)
```

## フロントエンド（React）

React ソースは**このリポ内 `frontend/`**（別リポではない）。各サブ dir が Vite+TS: `ranking`(ランキング) / `oc-app`(個別ルーム) / `all-room-stats`(全室統計)。

- **ビルド**: `cd frontend/<name> && npm run build` → `public/js/...` にハッシュ付きバンドル出力（成果物は **gitignore**、コミット不要）。手ビルドはローカル確認用。
- **PHP 参照**: `getFilePath('js/react','main-*.js')`（内部 glob、`app/Helpers/functions.php`）でハッシュ名を解決 → HTML 側の手修正は不要。
- **翻訳**: フロント側の翻訳 TS（`ranking` は `src/config/translation.ts`、`oc-app` は `src/graph/util/translation.ts`）は PHP の `storage/translation.json` と**別物**。両方直す。
- **デプロイ**: `deploy.yml` が `frontend/<name>/**` の変更を検知し自動で `npm ci && npm run build` → 配信。

## 広告出力とオプトアウト（合言葉で自分だけ広告を消す／X から来た人はそのセッションだけ消す）

### 広告出力の出口は3つだけ

`App\Views\Ads\GoogleAdsense` の `gTag()`（adsbygoogle.js 本体＝自動広告・オファーウォール）/
`loadAdsTag()`（`<ins>` への push）/ `output()`（広告枠）。部屋単位の全停止（`$suppressed`）も
オプトアウトも、**View 側の呼び出しを書き換えるのではなくこの3つの出口で一括して止める**。
新しい広告枠やページを足しても自動的に効く（消し忘れが起きない）。

### オプトアウトは必ずクライアント JS で判定する

`/admin/disable-ads` で合言葉を入れた人だけ広告が出なくなる仕組み。**サーバ側では判定できない**——
ページは Cloudflare の Cache Everything（＋オリジンの nginx 全キャッシュ）で配信されるため、
サーバが返す HTML は全訪問者で同一でなければならない。`/admin-check` のような非同期問い合わせも
返事が来る前に広告が走るので間に合わない。

- 合言葉 --HMAC(`$adOptOutSecret`)--> トークン（クッキー値・サーバでしか作れない）
- トークン --sha256--> ページ埋め込みハッシュ（JS が同期で突き合わせる・逆算できない）
- 実装: `App\Services\Ads\AdOptOutService`（導出・検証）/ `App\Views\Ads\AdOptOutGuard`（難読化JS出力）

`$adOptOutSecret`（ランダム鍵）は必須。これが無いと本リポが OSS である以上、合言葉候補を
総当たりして埋め込みハッシュと突き合わせる辞書攻撃でトークンが復元できてしまう。

### X プロフィールの通用口 `/x`（そのセッションだけ広告オフ）

X（旧Twitter）のプロフィール欄に `https://openchat-review.me/x` を置き、**踏んだ人はそのブラウザを
閉じるまで広告なし**にする裏口。合言葉は要らない。

- `/x` は `noStore()` でキャッシュさせず、`Set-Cookie`（**3時間で切れる**）を返して
  `/?utm_source=x&utm_medium=profile&utm_campaign=ad_free` へ 302。utm は GA4 で流入を数えるため。
  **Cloudflare 側に `/x` のキャッシュバイパスを入れてある**（ゾーンは Cache Everything なので、
  入れないと 302＋Set-Cookie がキャッシュされて機能が黙って死ぬ。oc-infra の `cloudflare/CHANGES.md` 参照）
- **セッションクッキー（expires 0）にしてはいけない**。Chromium は「前回開いていたタブを復元」や
  アップデート再起動でセッションクッキーごと復元するため、「閉じたら消える」が実態として成立しない
- クッキー名もトークンも**合言葉側と別**（`X_TOKEN_LABEL` / `X_COOKIE_LABEL`）。ガードは 2 本を独立に
  照合し、どちらか一致で広告オフ。分けている理由は 2 つ:
  - **X 経路だけ失効させられる**（`X_TOKEN_LABEL` の版番号を上げてデプロイ。`$adOptOutSecret` を回すと
    合言葉ユーザーまで巻き添えになる）
  - 同名だと、自分の X リンクを踏んだ瞬間に合言葉の永続クッキーがセッションクッキーで上書きされる
- **UA では「X から来たか」を判定できない**（iOS の SFSafariViewController は UA が Safari と同一、
  Android にも X 固有トークンは無い）。代わりに **Referer を必須**にしている（`isAllowedXEntryReferer()`）。
  X のリンクは必ず t.co を経由し、**t.co は実ブラウザに HTTP 200 の HTML＋JS リダイレクトを返す**
  （bot にだけ 301）ため、t.co がドキュメントとして読み込まれ転送先に `Referer: https://t.co/...` が付く。
  アプリ内ブラウザも UA は普通のブラウザなので同じ経路。よって **Referer 無し＝X 由来ではない**
  （ブックマーク・直打ち・コピペ）と見なして配らない。万一 Referer が落ちても広告が出るだけで安全側
- 追加の secrets は不要（既存の `$adOptOutSecret` から導出）。Cloudflare 側のルール追加も不要
  （`/admin/disable-ads` と同じく `noStore()` で Cache Everything を素通りする）

### 触るときの必守ポイント

- **フェイルオープン**: グローバルフラグは「広告オフのとき true」。昇格側は `if (フラグ) return;` と書く。
  逆向き（広告ONのとき true）にすると、ガードが動かなかったとき **全ユーザーの広告が消える**。
- **昇格スクリプトをガード本体に依存させない**: 復号関数などをガードの IIFE から参照すると、
  ガードが落ちたとき昇格側も ReferenceError で落ちて全ユーザーの広告が消える（実装時に実際に踏んだ）。
  昇格スクリプトは自前の復号関数を持つ自己完結型にする。
- **`:has()` は単独ルールにする**: 非対応ブラウザではカンマ区切りのルール全体が捨てられるため、
  枠を畳む CSS で `:has()` を他のセレクタと同じルールに混ぜない。
- 合言葉・鍵は Git に入れない。本番/stg は各サーバの `prod-secrets.php`、ローカルは `local-secrets.php`。
  未設定の環境では機能ごと無効（ページも 404）になり、広告まわりの挙動は従来と完全に同一。

## ページ系キャッシュ生成・genetop（必須ルールの詳細）

### 毎時・日次にキャッシュ生成を足したら genetop で全件実行できるようにする

毎時/日次の cron（`SyncOpenChat` の hourlyTask/dailyTask 等）にキャッシュ生成系の処理を追加したら、
必ず管理画面の **genetop**（`batch/exec/admin/genetop_exec.php`、AdminPageController から起動）からも
**全ルーム分を一括で再生成（全件）** できるようにする。genetop は「全キャッシュを全件作り直す」入口なので、
新しいキャッシュを足したらここにも全件モードで追加し、常に完全な状態を保つ。

理由: 毎時/日次は「変動した部屋・新規部屋」など一部しか回さないため、処理を足しただけでは既存の
大多数の部屋にキャッシュが行き渡らない（埋まるまで遅い、または未生成部屋がフォールバックの重い経路に
なる）。genetop があればデプロイ後に全件バックフィルできる。例: グラフ可用性メタ `chart_meta`
（oc_page_cache）は毎時/日次で増分生成しつつ、genetop が `UpdateOcPageCacheService::handle('')`
（mode=''＝全ルーム）で全件再生成する。

### ページ系キャッシュを変えたら README と管理画面フロー(log_index.php) も対で更新する

`oc_page_cache`（個別ルームの分析文・`chart_meta`）／おすすめ `.dat`／毎時・日次の cron フロー
（`SyncOpenChat`・`OcPageCacheGenerator`・`ChartMetaBuilder`・`RecommendStaticDataGenerator` 等）の
コードを変えたら、必ず次の2つも**同時に・対で**更新して齟齬を残さない:

- `README.md` の「ページ系キャッシュの生成アーキテクチャ」（Mermaid 図）
- 管理画面の処理フロー説明 `app/Views/admin/log_index.php`（毎時・日次の各ステップと GitHub リンク）

理由: これらは実装と対になる「正本ドキュメント」で、片方だけ更新すると現状を読み違える原因になる
（実際にこの周りはドキュメントが実装から取り残されやすい）。
