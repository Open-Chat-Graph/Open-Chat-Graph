次の機能でfeatureパイプラインを実行する: $ARGUMENTS

各段を順に実行する。先回りしない。各段の後、受け渡しファイルの存在を確認してから次へ進む。

1. `planner` サブエージェントに上記の機能要望を委譲。`.pipeline/spec.md` を待つ。
2. spec に OPEN QUESTION があれば止めて私に見せる。無ければ `coder` サブエージェントに委譲。`.pipeline/changes.md` を待つ。
3. `tester` サブエージェントに委譲。`.pipeline/test-results.md` を待つ。テスト失敗なら止めて失敗を見せる。
4. `reviewer` サブエージェントに委譲。`.pipeline/review.md` を見せる（UI変更ならスクショに基づくビジュアル判定込み）。

最終 VERDICT を報告する。マージはしない。ブランチは私のレビュー用に残す。

注意: DATA_PROTECTION=true。mock/ci/自動cron/DB破壊は実行しない。コミット/マージは人間の指示で行う。
