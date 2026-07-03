# TikTok 自動拡散・自動集客の設計書

デイリー急上昇ランキングを素材に縦型ショート動画を毎日自動生成し、TikTok へ投稿して
オプチャグラフ（openchat-review.me）への集客につなげる仕組みの全体設計。

- 実装状況: **Phase 1（動画の完全自動生成＋半自動投稿）実装済み**
- 関連コード: `app/Services/TikTokVideo/` / `batch/exec/tiktok_video_*.php` / `.github/workflows/tiktok-video.yml`
- README の概要図: [README.md「TikTok 動画の自動生成」](../../README.md#-tiktok-動画の自動生成集客)

---

## 1. 全体戦略（3フェーズ）

TikTok の完全自動投稿（公式 Content Posting API の Direct Post）は**アプリ審査が必須**で、
審査通過まで現実には 2〜6 週間かかる（§5）。そのため一気に完全自動を目指さず、
**動画生成だけ先に完全自動化して投稿は段階的に自動化する**。

| Phase | 投稿方法 | 自動度 | 状態 |
|---|---|---|---|
| **1** | 動画は毎日自動生成 → Discord に届く → スマホの TikTok アプリから手動投稿 | 生成=全自動 / 投稿=手動 | **実装済み** |
| **2** | Buffer / Later / Metricool 等の承認済みスケジューラ API 経由で自動投稿 | 全自動（即日導入可） | 未着手 |
| **3** | 自前 TikTok アプリで Direct Post（要アプリ審査） | 全自動（完全制御） | 任意・保留 |

Phase 1 の「アプリから手動投稿」には合理性がある: **API 投稿では TikTok の楽曲ライブラリが使えない**
（全スケジューラ共通の制限）。アプリ内投稿ならトレンド楽曲を付けられるため、フォロワーが少ない
立ち上げ期はリーチ面でむしろ有利。

### ナレーション（ずんだもん・実装済み）

日本語ペイロードは **VOICEVOX（ずんだもん・style 3）でナレーション音声を自動合成**して動画に載せる。

- エンジンは OSS の `voicevox/voicevox_engine:cpu-latest` を GitHub Actions のサービスコンテナで起動
  （レンダラーは `VOICEVOX_URL` で接続。未設定環境では無音で生成される）
- 台本は `TikTokNarrationBuilder`（ずんだもん口調・「第5位、〇〇。プラス987人なのだ。」）。
  部屋名は絵文字・記号を落とし読み上げは14文字まで（画面表示は省略しない）。読速 1.3倍
- **各スライドの表示時間はナレーションを読み終わるまで自動延長**（動画全体で40秒前後）
- **クレジット表記（規約必須）**: 「VOICEVOX:ずんだもん」を締めスライドとキャプションに自動付与
- BGM は引き続き付けない（投稿時にアプリ内でトレンド楽曲を重ねられる。ナレーションと共存可）
- 注意（§5）: TikTok の 2025 非オリジナルコンテンツ規制は「AI音声」を For You 除外要因に挙げる。
  ただし実データ・毎日更新・独自ビジュアルの組み合わせで機械的量産とは異なる構成であり、
  日本の TikTok ではずんだもん系コンテンツが広く流通している実態も踏まえ採用。数字で悪影響が
  見えたら無音版（アプリ楽曲のみ）に切り替えられる（VOICEVOX_URL を外すだけ）

### 台湾・タイ展開

スライドの文言は `t()`/`sprintfT()` によるロケール翻訳済み（translation.json にキー追加済み）で、
ペイロードの `urlRoot` を `/tw` `/th` にするだけで台湾版・タイ版が生成できる（動作確認済み）。
現状は `SyncOpenChat::dailyTask` のガード（`if (!MimimalCmsConfig::$urlRoot)`）で日本のみ
ディスパッチしている。展開時はこのガードを外し、TikTok アカウントを言語別に用意する。

---

## 2. アーキテクチャ（push 型にした理由）

```
本番 cron（日次処理の最後・23:30過ぎ・日本のみ）
  └ batch/exec/tiktok_video_dispatch.php （BatchScript::tiktokVideoDispatch）
      ├ TikTokVideoPayloadBuilder … statistics_ranking_hour24 から増加数 TOP5
      │   ＋各ルームの30日メンバー数系列（StatisticsChartArrayService）を収集
      └ GitHubVideoDispatcher … repository_dispatch(event_type=tiktok-video) で
          client_payload として GitHub へ送出（トークン未設定なら何もしない）

GitHub Actions（.github/workflows/tiktok-video.yml）
  ├ サービスコンテナ: voicevox/voicevox_engine:cpu-latest（ずんだもん音声合成）
  ├ setup-php(gd) + composer install --no-dev（ffmpeg はランナー標準装備）
  ├ batch/exec/tiktok_video_render.php payload.json out
  │   ├ TikTokNarrationBuilder + VoicevoxClient … ナレーション台本 → WAV（日本語のみ）
  │   ├ TikTokVideoSlideGenerator … GD で 1080x1920 スライド7枚
  │   │   （タイトル → 5位..1位カウントダウン → 締めCTA・VOICEVOXクレジット）
  │   └ TikTokVideoRenderer … ffmpeg（Ken Burns ズーム＋xfade＋ナレーションミックス）で mp4 合成
  ├ artifact アップロード（mp4＋caption.txt＋スライドPNG・14日保持）
  └ Discord 通知（動画添付≦9.5MB・キャプション付き）→ スマホで投稿
```

設計判断の背景:

1. **本番（スターサーバー＝共用ホスティング）で ffmpeg を動かさない。**
   root 無し・リソース制限があり、動画エンコードのような CPU 重負荷は不適切。
2. **GitHub Actions から本番の公開 API を pull しない（push 型）。**
   `/oclist` 等の API パスは Cloudflare WAF が「bot 以外の直叩き」を block する設計
   （過去に深 offset クロールで本番 DB が飽和した事故があり意図的に固い。oc-infra の
   cloudflare/CHANGES.md 参照）。Actions から pull するには WAF に穴を開ける必要があるが、
   本番 cron から push すれば CF 設定は一切触らずに済む。
3. **レンダラーは DB に触らない。** 必要データはペイロードに全部入っているので、
   Actions・ローカルどこでも同じコマンドで再現できる（fixture でのテストも同じ経路）。
4. **アイコンは LINE CDN（obs.line-scdn.net）から直接取得。** 公開 CDN なので Actions から
   届く。取得失敗時はプレースホルダ円で生成続行（OGP カードと同じ方針）。

### ペイロード形式（version 1）

`TikTokVideoPayloadBuilder::build()` が作り、`TikTokRisingVideoService::generate()` が受ける。
GitHub の client_payload 制限（トップレベル10キー・約64KB）内に収まる（実測 約10KB）。

```jsonc
{
  "version": 1,
  "urlRoot": "",              // '' | '/tw' | '/th'
  "generatedAt": "2026-07-02 23:45:00",
  "listType": "daily",
  "rooms": [                   // 順位順（添字0 = 1位）
    {
      "id": 101, "name": "...", "member": 18420,
      "increase": 1523,        // 24時間の増加数（statistics_ranking_hour24.diff_member）
      "percent": 9.0,          // 増加率（同 percent_increase）
      "iconUrl": "https://obs.line-scdn.net/.../preview",
      "dates": ["2026-06-03", ...],   // 30日
      "series": [17000, ...]          // メンバー数（null=欠測）
    }
  ]
}
```

---

## 3. 動画の仕様

- 1080x1920 / 30fps / H.264 + AAC（ずんだもんナレーション）/ 約40秒 / 実測 約2.9MB
  （VOICEVOX_URL 未設定環境では無音・約18秒）
- スライド: タイトル(2.0s) → 5位→1位（各3.2s・slideleft 遷移）→ 締めCTA(2.4s)。
  ナレーション付きの場合は各スライドが「読み終わるまで」自動延長される
- 演出: 各スライドに Ken Burns（ズームイン/アウト交互・2倍拡大でジッタ抑制）、xfade 0.45s
- 描画: OGP カード（`OcCardImageGenerator`）と共通の GD エンジン `GdTextRenderer` を継承。
  多言語フォントフォールバック（Noto CJK JP / Thai / カラー絵文字 / 記号）・円形アイコン・
  折り返しタイトルは全て共通コード。ブランドカラー（濃紺グラデ＋青アクセント）も OGP と統一
- CTA: TikTok はリンク導線が弱いため「『オプチャグラフ』で検索」の指名検索誘導を主 CTA にする
- キャプション: `caption.txt` に日付・TOP5 の部屋名と増加数・ハッシュタグを出力（コピペ用）

---

## 4. 運用手順

### 初期セットアップ（1回だけ・要オーナー作業）

1. **GitHub PAT の発行**: fine-grained PAT。対象リポ `Open-Chat-Graph/Open-Chat-Graph` のみ・
   権限は **Contents: Read and write** のみ（repository_dispatch に必要な最小権限）。
2. **本番 secrets に追記**（oc-infra の prod-secrets 管理経由）:
   ```php
   SecretsConfig::$gitHubVideoDispatchToken = '<PAT>';
   ```
   未設定の間はディスパッチが no-op なので、コードだけ先にデプロイしても安全。
3. **Discord Webhook**: 動画受け取り用チャンネルの webhook URL を GitHub リポの
   Actions secret **`TIKTOK_VIDEO_DISCORD_WEBHOOK`** に登録（未設定なら通知だけスキップ）。
4. **TikTok アカウント**: プロフィールにサイト URL・「毎日ランキング更新」を明記。

### 日々の運用（Phase 1）

- 毎日 23:30 の日次処理後、Discord に動画＋キャプションが届く
- スマホで保存 → TikTok アプリで投稿（トレンド楽曲を付ける・キャプションはコピペ）
- 投稿時間は翌朝〜昼推奨（TikTok のアクティブ帯。動画は前日データで内容は変わらない）

### テスト

- **Actions 手動実行**: workflow_dispatch → `docs/tiktok/fixture-payload.json` でレンダリング
- **ローカル**: `php batch/exec/tiktok_video_render.php docs/tiktok/fixture-payload.json out/`（要 ffmpeg）
- **本番ディスパッチ単体**: `php batch/exec/tiktok_video_dispatch.php`（トークン未設定なら no-op ログのみ）

### 計測

- 動画・プロフィールからの流入は GA4 で計測。プロフィールのリンクに `?utm_source=tiktok&utm_medium=social` を付ける
- 指名検索（「オプチャグラフ」）の増分は Search Console で確認（oc-infra 経由）

---

## 5. TikTok API 調査結果（2026-07 時点・公式ドキュメント確認済み）

Phase 2/3 の判断材料。出典は各項目末尾。

### 公式 Content Posting API

- **Direct Post**（フィードへ直接公開・完全自動化可）と **Upload to inbox**（下書き送信・
  ユーザーがアプリで投稿完了）の2モード。スコープは `video.publish` / `video.upload`
- **未審査アプリは SELF_ONLY（非公開）投稿のみ・5ユーザー/24h**＝テスト専用。
  公開投稿には**アプリ審査（audit）必須**
- 審査は公式「数日〜2週間」、実態は却下ラウンド込みで 2〜6 週間の報告が多い。
  審査には投稿 UX（ユーザー名/アバター表示・公開範囲セレクタ）実装とデモ動画が必要
- レート: Direct Post init は 6リクエスト/分/ユーザー、投稿は約15本/日/アカウント
- 出典: developers.tiktok.com — content-posting-api-get-started / content-sharing-guidelines /
  getting-started-faq / content-posting-api-reference-direct-post

### 規約・コンテンツポリシー（重要）

- **AIGC ラベル**: リアル系の AI 生成コンテンツはラベル必須。API に `is_aigc` フィールドあり。
  本件の動画は「実データの統計グラフィックス」でリアル系 AIGC ではないが、AI ツールで生成した
  ナレーション等を将来足す場合はラベル対象になり得る
- **2025年の非オリジナルコンテンツ規制**: 「新規性のある編集がない量産動画」「AI音声」は
  For You フィードから除外され得る（削除ではなく非推薦）。→ 実データ・毎日変わるランキング・
  独自ビジュアルという構成はここに引っかかりにくいが、**同一テンプレの機械的連投**と
  見なされないよう、投稿時のキャプション・楽曲は変化を付ける
- ボット的な大量投稿・「任意のコンテンツを他プラットフォームからコピーするアプリ」は
  ガイドラインで明示的に不可。1日1本×手動/承認済みツール経由なら問題ない範囲
- 出典: tiktok.com/community-guidelines/en/fyf-standards / support.tiktok.com（AI-generated content）/
  developers.tiktok.com content-sharing-guidelines

### Phase 2: 承認済みスケジューラ（審査不要・即日）

自前アプリの審査をスキップできる公式ルート。TikTok アカウントを OAuth 連携するだけ。

| ツール | 自動投稿 | 備考 |
|---|---|---|
| Buffer | ○ | 個人/ビジネス両対応。無料枠あり |
| Later | ○ | 公開動画1本以上が条件 |
| Metricool | ○ | ビジネスアカウントならトレンド楽曲 Top100 を API 付与可 |
| Hootsuite / Sprout | ○ | ビジネスアカウント必須・高価格帯 |

共通制限: TikTok 楽曲ライブラリ全体は使えない・カルーセルは不可の場合あり。
導入時は Actions ワークフローの最後に「スケジューラ API へ動画を POST」するステップを
足すだけで全自動化が完成する（レンダリング側の変更は不要）。

### Phase 3 に進む判断基準

- Phase 2 で 3ヶ月運用して流入が明確に立っている（GA4 で判断）
- 楽曲なし・テンプレ動画でも再生が回っている（API 投稿の制限が実害にならない）
- 多言語×複数アカウントでスケジューラの料金が自前実装コストを上回る

---

## 6. 今後の拡張候補（v2 以降）

- **数字のカウントアップ演出**: 全フレーム GD 描画 or ffmpeg drawtext 式で「+1,523」が動的に増えていく表示
- **グラフの描画アニメーション**: 空グラフ→完成グラフの xfade wipeleft（現在はスライド全体の演出のみ）
- **コンテンツの型追加**: カテゴリ別 TOP5（曜日ローテ）／週間ランキング（日曜）／
  「1週間で+N人」個別ルーム深掘り型
- **サムネ最適化**: スライド1枚目を「1位の部屋名入り」にする等の CTR 実験
- **多言語ナレーション**: ずんだもんは日本語のみ。台湾・タイ展開時は各言語 TTS を別途選定
