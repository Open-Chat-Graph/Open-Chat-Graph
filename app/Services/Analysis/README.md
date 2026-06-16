# 詳細成長分析（/analysis）

専門ユーザー（公認メンター・データ分析者・詳しい管理者）向けの重い分析ページ。通常のランキング
（毎時・24時間・週間）では埋もれる **長期の増加** や **数年かけてじわじわ伸びている部屋** を抽出する。
公開ページだが **noindex**。

## 何ができるか

- **期間の増加**（metric=increase）: 1ヶ月 / 1年 / 任意期間(from–to) の増加数・増加率。
  「1年」なら *いま存在し、かつ1年前にも存在した* 部屋だけが対象。増加数/増加率で昇順・降順ソート、
  カテゴリ・キーワードで絞り込み。
- **じわじわ成長スコア**（metric=steady）: 全履歴の線形回帰で「安定して長期に伸びた部屋」を抽出。
  スコア＝ `R²² × 継続年数 × log(1+増加量) × 暴落減点`。安定性(R²)と継続年数を重視し、規模の暴発は
  対数で抑える。表示は 年率(CAGR)・安定度(R²)・継続年数。

## アーキテクチャ（cron・事前計算なし。検索のたびにライブ計算）

通常ランキングと違い、全ルーム横断・期間指定のクエリは 1億行超の SQLite `statistics`
（インデックスは `(open_chat_id, date)` のみ）に対し重い。そこで **ポーリング駆動のチャンク計算** で
分割実行し、本物の % 進捗を出す。

```
[React /analysis]                         [PHP]
  AnalysisToolbar ──検索──▶ GET /analysis-status ──▶ AdvancedGrowthAnalysisService::advance()
   (本物の%進捗 + キャンセル)   (1チャンクずつ計算)      open_chat_id レンジを CHUNK_COUNT 分割し
                                                       1回につき1チャンクを集約 → 進捗ファイル更新
                              ◀── {done,percent,computed}   最終チャンクでマージ・ソート → 結果ファイル
  完了後にまとめて取得 ───▶ GET /analysis-result ──▶ ::result()  結果をフィルタ/ソート/スライス/ハイドレート
   (クライアント側で無限スクロール描画)            ◀── OpenChat風アイテム[]（先頭に totalCount）
  条件変更で再検索 / 中断 ─▶ GET /analysis-cancel ──▶ ::cancel()  中間ファイルを掃除
```

- **「セッションが切れても再計算しない」**: 状態と結果は **ファイル**が持つ（`storage/{locale}/analysis_jobs/`）。
  キャッシュキー = `(locale, metric, period/from/to)` ＋ 毎時更新時刻(hour)。2バッチ目以降や同一検索の
  再訪はファイルから返す。重い計算は **(クエリ × 時間帯) で1回だけ**。
- **CDN**: 毎時クロールでしか変わらない＝1時間は同一データ。`/analysis-result` は
  `checkLastModified(@hourlyCronUpdatedAtDatetime)` を付け、同一 URL クエリを Cloudflare がキャッシュ。
  `/analysis-status` `/analysis-cancel` は no-store。
- **キャンセル**: クライアントがポーリングを止める＝計算が進まない（OSプロセスは起動しない）。
  `cancel` は中間ファイルも掃除する。
- **大量一括取得＋クライアントページング**: 完成結果から最大 3000 件を一括取得し、無限スクロールは
  メモリ内スライスで描画（スクロールのたびに再クエリしない）。超過分のみ次バッチを取得。

### 性能の要点

- 期間増加: 終点が最新日なら現在値＝`open_chat.member` を使い、as-of クエリを1本省く（全履歴走査を回避）。
- じわじわ: 回帰の総和を SQLite 集約で 1ルーム1行に畳む。member 行参照が重いので各月 01/11/21 日に
  間引いて約7〜10倍速（長期トレンドの精度には影響軽微）。回帰の x は桁落ち回避のため julianday から
  固定オフセットを引く。
- 足切り: じわじわは 履歴≥365日・現在≥50人・サンプル≥24点。増加率ソートは base≥50 で極小暴騰%を除外。

## 主なクラス

| 役割 | クラス |
| --- | --- |
| 指標計算（純粋関数） | `App\Services\Analysis\GrowthMath` |
| ジョブ実行・結果整形 | `App\Services\Analysis\AdvancedGrowthAnalysisService` |
| 統計集計(SQLite) | `AnalysisStatsRepositoryInterface` → `SqliteAnalysisStatsRepository` |
| 部屋メタ(MySQL open_chat) | `AnalysisRoomRepositoryInterface` → `AnalysisRoomRepository` |
| API | `App\Controllers\Api\AdvancedGrowthAnalysisApiController`（status/result/cancel） |
| ページ | `App\Controllers\Pages\ReactAnalysisPageController` ＋ `app/Views/analysis_react_content.php` |

フロントは `frontend/ranking`（/ranking と同一バンドル。React Router が URL で出し分け）:
`pages/AnalysisPage` `components/AnalysisToolbar` `components/AnalysisListItem`
`components/FetchAnalysisList` `hooks/AnalysisHooks`。

`?run=1` を付けた URL は読み込み時に自動で分析を実行する（共有・ブックマーク用ディープリンク）。

テスト: `docker compose exec app vendor/bin/phpunit app/Services/Analysis/test/GrowthMathTest.php`
