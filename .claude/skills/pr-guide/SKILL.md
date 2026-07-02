---
name: pr-guide
description: PR・コミット作法の詳細。タイトル/本文の書き方（一般人向け・用語の言い換え）、UI スクリーンショットの撮り方と添付、skip-ci/skip-post の挙動と例外、本番デプロイの確認手順、環境署名フォーマット。PR 作成・コミット・マージ・デプロイ確認の前に読む。
---

# PR・コミットガイド

> 前提: CLAUDE.md の必守ルール（スクショ添付・デプロイ見届け・署名・skip-ci デフォルト方針）に従う。本ガイドはその具体的な手順と書き方。

## 画面(UI)を変えた PR はスクリーンショットを本文に必ず添付する

画面に機能を追加・改修した PR は、**実装後の各画面のスクリーンショットを PR 本文に貼る**。文章だけだと
レビュー側がどんな見た目になるか判断できないため。

- 撮り方: 実環境（`.env` の `HTTPS_PORT`）を headless Chrome（`google-chrome --headless=new
  --ignore-certificate-errors --screenshot=...`）かブラウザで開いて撮る。エラー画面・5xx・空状態など特殊な
  状態は、対象エンドポイントに一時的に例外を throw する等で再現してから撮り、**撮影後に必ず元へ戻す**。
- 添付方法: GitHub web UI へのドラッグ&ドロップで添付する（CLI から GitHub の画像 CDN へ直接アップロードは
  できない）。**スクショホスティング用のブランチ（`assets/pr-screenshots` 等）は作らない**（2026-06-27 ユーザー指示）。

## 報告は本番デプロイ完了まで待つ

PR をマージして終わりにしない。**本番デプロイ（`deploy.yml` の Deploy job）が success になるまで見届けてから「完了」と報告する**。マージ＝デプロイ成功ではない（別 PR が `deploy.yml` 等に残した不整合で本番デプロイだけが落ちることがある）。

- main マージ後、`gh run list --workflow=deploy.yml --limit 4` で該当 Deploy run を特定し、`gh run view <id> --json status,conclusion` を completed/success までポーリングする。本番かどうかの確実な見分け方は env `IS_STG: false`（run タイトルの `[PROD]` は `pr-title-prefix.yml` が main 向け PR タイトルに自動付与したもの）。
- 失敗したら原因を直して再デプロイし、success を確認してから報告する。
- 本番 SSH はしない（確認は Actions の結果まで）。

## 環境署名（全コミット・全 GitHub 投稿）

GitHub に投稿する本文（PR 本文・issue・PR/issue コメント等）の**末尾**に、区切り線を入れて以下の署名ブロックを付ける。どのマシン・ディレクトリから、どのモデル・ツールで投稿されたかを残すため。

```markdown
---
🤖 Generated with Claude Code (<モデルID>)
Posted from: `<hostname>:<作業ディレクトリ>`
```

- モデルはその時のセッションの実際のモデル ID を書く（表示名は省く。例: Opus 4.8 → `claude-opus-4-8[1m]`）
- `<hostname>` は `hostname`、`<作業ディレクトリ>` は `pwd` の値だが**ホームディレクトリは `~` に短縮**する（例: `/home/user/repos/Open-Chat-Graph` → `user-B550M-Pro4:~/repos/Open-Chat-Graph`）
- **コミットメッセージにも毎回 環境署名を入れる**（PR/issue/コメント本文だけでなく、全コミット）。末尾に署名2行を付ける。`Co-Authored-By: Claude ...` は 🤖 行とモデル情報が重複するので**付けない**（全廃）:

  ```
  <コミット本文>

  🤖 Generated with Claude Code (claude-opus-4-8[1m])
  Committed from: user-B550M-Pro4:~/repos/Open-Chat-Graph
  ```

  - モデル・hostname・ディレクトリの書き方は上記と同じ（ホームは `~` 短縮、リポごとに実ディレクトリを書く）。
  - **このルールは infra リポ(oc-infra)など他リポのコミットにも全て適用する**。

## タイトルの書き方

**IMPORTANT**: PR タイトルは SNS に自動投稿されるため、一般人に伝わる書き方にする。

❌ BAD:

```
perf: dailyTask処理時間の大幅短縮とタイムアウト問題の解決
fix: getMemberChangeWithinLastWeekCacheArray()の重複実行を防止
```

✅ GOOD:

```
perf: 日次データ更新処理のタイムアウト問題を解決（9〜11時間→1〜2時間）
fix: 統計データ抽出クエリの重複実行を防止してDB負荷を軽減
```

ガイドライン:

- コード用語（クラス名・メソッド名・変数名）を避ける
- 具体的な数値を入れる（処理時間・データ量）
- 技術詳細ではなく業務影響を説明する

## 本文の書き方

構成:

1. 業務・ユーザーへの影響から始める
2. 技術的な問題を平易な言葉で説明する
3. 該当コード箇所へリンクする
4. 実装の詳細を書く

例:

```markdown
## 問題の概要

オープンチャットの日次データ更新処理が9〜11時間かかり完了しない

### 具体的な問題

全statisticsテーブル（8700万行）から「メンバー数が変動している部屋」を抽出する処理が、
以下の2箇所で重複実行されている:

- クローリング対象の絞り込み処理 ([`DailyUpdateCronService::getTargetOpenChatIdArray()`](link))
- ランキング用キャッシュ保存処理 ([`UpdateHourlyMemberRankingService::saveFiltersCacheAfterDailyTask()`](link))

## 対処内容

クエリ結果をプロパティに保存し、2回目で再利用
```

### 用語の言い換え

- dailyTask → オープンチャットの日次データ更新処理（毎日23:30実行）
- hourlyTask → オープンチャットの毎時ランキング更新処理（毎時30分実行）
- getMemberChangeWithinLastWeekCacheArray → 統計データ抽出処理（メンバー数が変動している部屋を取得）

## skip-ci / skip-post

### skip-ci

CI Test (`ci.yml`) は Mock 環境のクローリング＋URLテストのみで、**phpunit は実行しない**。
これらが意味を持たない変更（typo・ドキュメント・デプロイ時にだけ動くロジック等）では CI を飛ばせる:

- PR に `skip-ci` ラベルを付ける
- または PR タイトルを `skip-ci:` 始まりにする（例: `skip-ci: Fix typo in README`）

**デフォルト方針（PHP を触らない PR は skip-ci）:**
CI(`ci.yml`)は Mock 環境のクローリング＋URLテストなので、その挙動は基本的に PHP 側で決まる。
そのため **PHP コードを一切変更していない PR は、デフォルトで skip-ci にする**（フロント JS/CSS・
ドキュメント・翻訳 JSON 等だけの変更）。確実に効かせるため **PR タイトルを `skip-ci:` 始まりにする**
（ラベルだけは PR 作成時にレースして効かないことがある。既存 PR に後付けする場合は、ラベルを
付けてから次の push をすると `synchronize` で再評価されて効く）。

注意: stg/main 向け PR はタイトル先頭に `[STG]`/`[PROD]` が自動付与される（`pr-title-prefix.yml`）ため、
付与後の push ではタイトル判定が効かなくなる。確実なのは「タイトル `skip-ci:` 始まり（opened 時に判定される）
＋ `skip-ci` ラベル（deploy ゲート用）」の併用。

**例外（PHP を触っていなくても skip-ci を付けない）:**

- ルーティング・ページ表示に影響しうる View テンプレート/JS の変更（URLテストで表示崩れ・500 を拾える）
- mock 環境・クローラ設定・依存（composer/npm）・CI 設定(`ci.yml`/`docker-compose.ci.yml`)自体の変更
- 挙動への影響に少しでも不安がある変更

**skip-ci 使用時の挙動:**

- CI テスト（`ci.yml` の Mock クローリング＋URLテスト）がスキップされる
- `deploy.yml` の「Check CI status」ゲートも飛ばされる（CI 成功を要求しなくなる）
- **デプロイは止まらない**。PR がマージされれば deploy job は通常どおり走り、stg/本番へ反映される
  （deploy job の発火条件はマージ/手動実行だけで、skip-ci はデプロイを止めない）
- 補足: `stg` を head にした PR は skip-ci 無しでも CI が自動スキップされる（`ci.yml`）

### skip-post

マージ後の X (Twitter) 自動投稿だけをスキップする（CI とデプロイは走る）:

- PR に `skip-post` ラベルを付ける
- または PR タイトルを `skip-post:` 始まりにする（例: `skip-post: Internal configuration update`）

X 自動投稿はリリース履歴を兼ねるため、**skip-post を自己判断で付けない**（ユーザー指示があるときだけ）。
