<!DOCTYPE html>
<html lang="ja">
<?php viewComponent('head', compact('_css', '_meta')) ?>

<body>
    <?php viewComponent('site_header') ?>
    <main class="no-pad blog-main">
        <article class="blog blog-article">
            <nav class="blog-crumb">
                <a href="<?php echo url('') ?>">トップ</a><span class="sep">›</span>AI連携（MCP）
            </nav>

            <h1 class="blog-title">AIにオプチャグラフを接続する｜ChatGPT・Claudeでオープンチャットのデータを調べる</h1>

            <div class="blog-meta">
                <span class="author">オプチャグラフ</span>
                <span>公開 2026-07-17</span>
                <span class="cat">使い方</span>
            </div>

            <div class="blog-body">
                <p>オプチャグラフが毎時記録しているオープンチャットの統計データを、ChatGPTやClaudeといったAIチャットから<b>直接</b>使えるようになりました。「いま伸びてる部屋は？」とAIに聞くだけで、AIが自分でオプチャグラフのデータを調べて答えてくれます。</p>
                <p>仕組みはMCP（Model Context Protocol）という、AIに外部のデータを接続するための共通規格です。<b>登録・申請・料金は一切不要</b>。AIアプリの設定画面に、次のURLを1行貼るだけです。</p>
                <pre><code>https://openchat-review.me/mcp</code></pre>

                <div class="blog-point">
                    <p class="blog-point__label">結論</p>
                    <ul>
                        <li><b>設定はURLを1行貼るだけ。</b>登録・申請・追加料金なし。</li>
                        <li>「いま伸びてる雑談部屋は？」と<b>AIに聞くだけ</b>で、毎時更新の実データから答えが返る。</li>
                        <li>対象は<b>現在掲載中の約24万室</b>。2023年10月からのメンバー数推移1.2億件ぶん。</li>
                    </ul>
                </div>

                <h2>なにができる？</h2>
                <p>接続すると、AIがオプチャグラフのデータベースを自分で検索・集計して答えられるようになります。たとえばこんな質問がそのまま通ります。</p>
                <ul>
                    <li>「いま<b>24時間で一番伸びてる</b>オープンチャットを教えて」</li>
                    <li>「『ポケモン』の部屋を<b>人数順</b>に並べて、それぞれの特徴をまとめて」</li>
                    <li>「この部屋の<b>直近24時間のメンバー数と公式ランキング順位</b>の動きを分析して」（部屋のURLやIDを渡す）</li>
                    <li>「LINE公式ランキングから<b>掲載が消えた部屋</b>にはどんな傾向がある？」</li>
                </ul>
                <p>データは<a href="<?php echo url('ranking') ?>">ランキング</a>や各部屋のグラフと同じもので、毎時更新。返ってくるのは現在掲載中の部屋だけなので、AIがすでに消えた部屋をすすめてしまう心配もありません。</p>

                <h2>どのアプリで使える？</h2>
                <p>MCPに対応しているAIアプリなら使えます。2026年7月時点の代表例です。</p>
                <ul>
                    <li><b>Claude</b>（クロード）… 有料プラン（Pro以上）の「コネクタ」機能で追加できます。<b>一度設定すればスマホアプリでもそのまま使えます</b>。</li>
                    <li><b>ChatGPT</b> … 有料プラン（Plus以上）の「コネクタ」機能で追加できます。こちらも設定後はスマホアプリから使えます。</li>
                    <li><b>Gemini CLI</b>（ジェミナイ）… <b>無料で使える数少ない選択肢</b>。Googleアカウントがあれば無料枠で使えます。ただしパソコンの「ターミナル」で動く黒い画面のツールなので、少し敷居は高めです（下に設定方法）。</li>
                    <li><b>Claude Code・Cursor・VS Code など</b> … 開発ツール系はほぼ全対応（エンジニア向け）。VS Code（GitHub Copilot）は無料枠でもMCPが使えます。</li>
                </ul>
                <p><b>スマホだけで完結したい場合</b>: ClaudeやChatGPTの有料プランなら、スマホのブラウザで claude.ai / chatgpt.com を開いて上記の設定を一度行えば、あとはスマホアプリだけで使えます。<b>無料かつスマホだけ</b>で使える方法は、残念ながら今のところ見つかっていません。</p>
                <p>対応アプリは増え続けているので、「このアプリでも使えた」という情報は<a href="https://x.com/openchat_graph" target="_blank">X (@openchat_graph)</a>まで教えてもらえると嬉しいです。</p>

                <h2>つなぎ方（1分）</h2>
                <h3>Claudeの場合</h3>
                <ol>
                    <li>パソコンのブラウザで claude.ai を開き、<b>設定 → コネクタ</b>を開く</li>
                    <li>「カスタムコネクタを追加」を選ぶ</li>
                    <li>名前に「オプチャグラフ」、URLに <code>https://openchat-review.me/mcp</code> を貼って保存</li>
                    <li>チャットでそのままオープンチャットについて質問する</li>
                </ol>
                <h3>ChatGPTの場合</h3>
                <ol>
                    <li>パソコンのブラウザで ChatGPT の<b>設定 → コネクタ</b>を開く（見当たらない場合は設定内で「開発者モード」を有効にする）</li>
                    <li>「作成」からURLに <code>https://openchat-review.me/mcp</code> を貼って追加（認証は「なし」でOK）</li>
                    <li>チャットでそのままオープンチャットについて質問する</li>
                </ol>
                <h3>Gemini CLI の場合（無料）</h3>
                <ol>
                    <li>パソコンに <a href="https://github.com/google-gemini/gemini-cli" target="_blank">Gemini CLI</a> をインストールして、Googleアカウントでログインする</li>
                    <li>ホームフォルダの <code>.gemini/settings.json</code> に次を追記する</li>
                </ol>
                <pre><code>{
  "mcpServers": {
    "openchat-graph": {
      "httpUrl": "https://openchat-review.me/mcp"
    }
  }
}</code></pre>
                <p>エンジニア向けの接続コマンドやSQLの仕様は<a href="https://github.com/Open-Chat-Graph/Open-Chat-Graph/blob/main/API_README.md" target="_blank">データAPIドキュメント（GitHub）</a>にまとめてあります。</p>

                <h2>データの範囲と約束ごと</h2>
                <ul>
                    <li>対象は<b>現在オプチャグラフに掲載中の部屋（約24万室）</b>。メンバー数の日次推移は2023年10月から1.2億レコード以上、毎時更新。</li>
                    <li>LINE公式「ランキング」「急上昇」の順位履歴や、<a href="<?php echo url('labs/publication-analytics') ?>">ランキングから掲載が消えた記録</a>も分析できます。</li>
                    <li>コメント投稿者の情報など、<b>個人情報を含むデータは公開していません</b>。</li>
                    <li>利用回数の制限はありません。ただし同時に処理できるのはサイト全体で2件までです（非力なサーバーなので手加減してもらえると助かります）。</li>
                    <li>データを引用・紹介するときは、出典として「<b>オプチャグラフ (openchat-review.me)</b>」と部屋ページのURLを添えてもらえると嬉しいです。</li>
                </ul>
            </div>

            <?php viewComponent('share_buttons', [
                '_shareUrl' => url('mcp'),
                '_shareGa' => ['content_type' => 'mcp_guide', 'item_id' => 'mcp'],
            ]) ?>

            <div class="blog-cta">
                <div class="blog-cta-h">📈 オプチャグラフで<span class="em">実データ</span>を見る</div>
                <div class="blog-cta-row">
                    <a class="blog-btn blog-btn--primary" href="<?php echo url('ranking') ?>">人気ランキング</a>
                    <a class="blog-btn" href="<?php echo url('') ?>">急上昇テーマ</a>
                    <a class="blog-btn" href="<?php echo url('labs/publication-analytics') ?>">掲載・圏外の分析</a>
                </div>
            </div>
        </article>
    </main>
    <?php // トップ以外はオファーウォール継続の方針（display広告ユニットは全停止中） ?>
    <?php \App\Views\Ads\GoogleAdsense::gTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>
