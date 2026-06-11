# sitemap の更新日 (lastmod)

各部屋ページ `/oc/{id}` を sitemap でGoogleに伝えるとき、その「最終更新日(lastmod)」を
**ページ内容が実際に変わった日**にするための仕組み。

元々は `open_chat.updated_at` を使っていたが、これはタイトル・説明・ステータスといった
メタ情報を変えたときしか動かない。ページの主役は現在人数・推移・分析(narrative)で、これらは
人数で変わる。そのため人数が伸びている部屋ほど更新日が古いまま放置され、Googleに
「変わっていないページ」と誤って伝わり、再クロールが後回しになっていた。

## 更新日が動く条件

専用テーブル `oc_sitemap_lastmod` に「内容が変わった日」を独立して持つ。次のいずれかで更新:

- メタ情報が変わった (`open_chat.updated_at` がレコードより新しい)
- 人数が**意味のある幅**で変わった = 前回時点の人数の 1%、ただし最低 5 人

数人程度の揺れは無視する。毎回ちょっとの増減で更新日を動かすと、Googleは更新日全体を
信用しなくなり逆効果になるため。閾値は `max(ceil(人数 × 1%), 5)`。

## 構成

- 閾値の仕様(唯一の正): [`LastmodPolicy`](LastmodPolicy.php)
- 日次の一括更新SQL: [`OcSitemapLastmodRepository`](../../Models/Repositories/OcSitemapLastmodRepository.php)
  ([`SyncOpenChat::dailyTask`](../Cron/SyncOpenChat.php) から毎日実行 → 直後の sitemap 生成が読む)
- sitemap 読み取り: `COALESCE(専用テーブル, 従来のupdated_at)` で未登録の部屋は従来どおり
  ([`OpenChatListRepository::getOpenChatSiteMapData`](../../Models/Repositories/OpenChatListRepository.php))

閾値の式は `LastmodPolicy` と更新SQLの両方に現れるので、変えるときは両方を一致させること
(`LastmodPolicy` が仕様の正)。詳細は各ファイルのコメント参照。

## 初期化 (一度きり・手動・完了済み)

テーブルはデプロイ時に schema-sync が自動で作る([../Schema/README.md](../Schema/README.md))。
初期データ投入は locale ごとに1回 `batch/exec/seed_sitemap_lastmod.php` を手動実行する手順だったが、
**全 locale で投入完了に伴いスクリプトは削除済み**（必要になったら git 履歴から復元する）。

JP は分析(narrative)を全部屋に載せた日を下限にして更新日を底上げし、再クロールの波を起こした。
TW/TH は底上げせず最小日時を下限にしただけ。以後の維持は日次更新が担う。
