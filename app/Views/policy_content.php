<!DOCTYPE html>
<html lang="<?php echo t('ja') ?>">
<?php

use App\Views\Ads\GoogleAdsense as GAd;

viewComponent('policy_head', compact('_css', '_meta')) ?>

<body>
    <script type="application/json" id="comment-app-init-dto">
        <?php echo json_encode(['openChatId' => 0, 'baseUrl' => url()], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    </script>
    <script type="module" crossorigin src="/<?php echo getFilePath('js/comment', 'index-*.js') ?>"></script>
    <?php viewComponent('site_header') ?>
    <main style="overflow: hidden;">
        <article class="terms">
            <?php if (\Shared\MimimalCmsConfig::$urlRoot === ''): ?>
                <h1 style="letter-spacing: 0px;">オプチャグラフとは？</h1>
                <p>オプチャグラフはLINEオープンチャットの成長傾向をグラフやランキングで比較できるWEBサイトです。</p>
                <p>LINE非公式の<a href="https://github.com/pika-0203/Open-Chat-Graph" target="_blank">オープンソースプロジェクト</a>により運営されています。 </p>
                <h2>サイトの目的</h2>
                <p>・ユーザーがオープンチャットを見つけて参加する機会を作る</p>
                <p>・オープンチャットの管理者が成長傾向を把握し、比較できる事で運営に役立つ</p>

                <h2>オープンチャットの情報を掲載する仕組み</h2>
                <p>
                    オプチャグラフは「<a href="https://openchat.line.me/jp" rel="external" target="_blank">LINEオープンチャット公式サイト</a>」のデータを基に、グラフやランキングを作成して掲載しています。
                </p>
                <p>
                    最新データを表示するため、オプチャグラフの<a href="https://webtan.impress.co.jp/g/%E3%82%AF%E3%83%AD%E3%83%BC%E3%83%A9%E3%83%BC" target="_blank">クローラー（巡回プログラム）</a>が公式サイトを定期巡回して、オープンチャットのデータをインデックス（記録）しています。
                </p>
                <p>
                    <b>データの取得は公式サイトのみから行います。LINEアプリ本体に係るデータを取得することはありません。</b>
                </p>
                <section style="margin: 1rem 0;">
                    <h3>オプチャグラフに掲載される条件</h3>
                    <p>
                        オプチャグラフのクローラーは<a href="https://openchat.line.me/jp/explore?sort=RANKING" rel="external" target="_blank">公式サイトのランキング</a>から新しいルームを見つけて登録します。<b>ランキングに掲載されていないルームは登録されません。</b>
                    </p>
                    <p>
                        一度オプチャグラフに登録されたルームはランキング掲載の有無に関わらず、引き続きオプチャグラフに掲載されます。
                    </p>
                    <p>
                        公式サイトにて掲載が終了・削除されたルームは、オプチャグラフから削除されます。
                    </p>
                    <p>
                        ランキングに未掲載（開設して間もないなど）のルームは、公式サイトに掲載されている場合に限り、手動でオプチャグラフに登録できます。
                    </p>
                    <p>
                        <a href="<?php echo url('oc') ?>">オープンチャットを手動で登録する</a>
                    </p>
                    <p>
                        <a href="<?php echo url('recently-registered') ?>">最近登録されたオープンチャット</a>
                    </p>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>公式サイトでの掲載条件</h3>
                    <p>
                        オープンチャットの検索を許可しているなどの条件において、開設したルームが公式サイトに掲載されます。
                    </p>
                    <p>
                        検索をOFFに設定変更した場合、公式サイトの掲載が削除され、オプチャグラフからも削除されます。
                    </p>
                    <p>
                        参考ページ: <a href="https://openchat-jp.line.me/other/notice_webmain_3gf87gs1" target="_blank">Webブラウザ版メイン画面公開と検索エンジン対応のお知らせ | LINEオープンチャット</a>
                    </p>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>情報更新のスケジュール</h3>
                    <p>
                        オプチャグラフのクローラーは公式サイトを定期巡回してルームのタイトル、説明文、画像、人数統計、ランキング履歴などを更新します。
                    </p>
                    <ul style="font-size: 18px; line-height: 2;">
                        <li>ランキング掲載中のルーム: 1時間毎（毎時30分頃）</li>
                        <li>ランキング未掲載のルーム: 1日毎 （23:30〜0:30頃）</li>
                        <li>ランキング未掲載かつ1週間以上メンバー数に変動がないルーム: 1週間毎</li>
                    </ul>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>キーワード検索機能について</h3>
                    <p>
                        オプチャグラフが提供するキーワード検索機能は公式サイトからインデックスした情報に基づいています。Google・Yahoo・Bingなどの検索サイトが表示する検索結果と同様の内容です。
                    </p>
                    <p>
                        LINE公式のキーワード検索機能とオプチャグラフはリンクしておらず、異なるものです。オプチャグラフが公式の検索機能からデータを取得することはありません。
                    </p>
                    <p>
                        <b>オプチャグラフはLINE公式の検索機能について関与していません。公式の検索機能にルームが表示されない理由を調べることはできません。</b>
                    </p>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>ランキングの順位グラフについて</h3>
                    <p>
                        オプチャグラフのクローラーは公式サイトのランキング順位を1時間毎に記録します。ルームの並び順から順位を数えて算出しています。
                    </p>
                    <p>
                        ランキングに掲載がなかったルームは「圏外」として記録されます。ルーム管理者によるルーム情報（タイトル、説明文、画像）の更新後や、サーバーエラーなどでも圏外になる場合があります。
                    </p>
                    <p>
                        <b>オプチャグラフはLINE公式のランキング掲載基準について関与していません。ルームの審査基準等を調べるためのツールではありません。</b>
                    </p>
                </section>
                <h2>オプチャグラフ公開の経緯</h2>
                <p>
                    オプチャグラフの公開が可能になった経緯として、オプチャ公式による検索エンジンへの対応が始まった事があげられます。
                </p>
                <p>
                    オープンチャットのサービス開始当初、オープンチャットを検索したり、ランキングを見る機能はLINEアプリ内限定で提供されていました。
                </p>
                <p>
                    しかし、2023年10月頃に<a href="https://openchat.line.me/jp" target="_blank">「WEBブラウザ版メイン画面」（公式サイト）</a>が公開された事により、LINEアプリ外のブラウザからオープンチャットを検索したり、ランキングを見ることが可能になりました。
                </p>
                <p>
                    参加経路の拡大を図るため、「WEBブラウザ版メイン画面」に掲載されているオープンチャットを検索エンジン（Googleなど）の検索結果に表示させるというものです。
                </p>
                <p>
                    <a href="https://ja.wikipedia.org/wiki/%E6%A4%9C%E7%B4%A2%E3%82%A8%E3%83%B3%E3%82%B8%E3%83%B3%E6%9C%80%E9%81%A9%E5%8C%96" target="_blank">SEO</a>と言われるマーケティングの一環により、検索エンジンに積極的な掲載を図りユーザーの認知を増やすことを目的とした媒体であると考えられます。
                </p>
                <p>
                </p>
                <p>
                    <b>オプチャグラフは「WEBブラウザ版メイン画面」の分析データを掲載し、参加経路拡大に寄与するために開発されたオープンチャット専用の検索エンジンです。</b>
                </p>
                <p>
                    オプチャグラフのクローラーが公式サイトのデータをクローリングし、Google・Bingなどの検索エンジンと同様の一般的なルールに基づいてデータを公開しています。
                </p>
                <p>
                    オープンチャットのデータを不正に公開するものではなく、適切な範囲内で健全な情報共有を行うWEBサイトです。
                </p>
                <p>
                    参考ページ: <a href="https://openchat-jp.line.me/other/notice_webmain_3gf87gs1" target="_blank">Webブラウザ版メイン画面公開と検索エンジン対応のお知らせ | LINEオープンチャット</a>
                </p>
                <br>
                <p>
                    オープンチャットの参加経路拡大に寄与するため、オプチャグラフのページもSEOを考慮した設計となっています。
                    <span id="comments" aria-hidden="true"></span>
                </p>

                <h2 style="margin-bottom: 2rem;">オプチャグラフに関する情報共有・コメント</h2>
                <div style="min-height: 400px;">
                    <div id="comment-root"></div>
                </div>

                <h2>メールでのお問い合わせ先</h2>
                <p>オプチャグラフお問い合わせ窓口: <a href="mailto:support@openchat-review.me">support@openchat-review.me</a></p>
            <?php elseif (\Shared\MimimalCmsConfig::$urlRoot === '/tw') : ?>
                <h1 style="letter-spacing: 0px;">關於LINE社群流量統計</h1>
                <p>LINE社群流量統計是一個幫助使用者發現開放聊天室，並透過圖表和排名比較成長趨勢的網站。</p>
                <p>這是一個由 <a href="https://github.com/pika-0203/Open-Chat-Graph" target="_blank">開源專案</a> （LINE非官方）營運。</p>
                <h2>網站的目標</h2>
                <p>・為使用者提供機會找到並加入開放聊天室</p>
                <p>・協助開放聊天室的管理員掌握成長趨勢並進行比較，對管理運營有所幫助</p>

                <h2>開放聊天室資訊的收錄機制</h2>
                <p>
                    LINE社群流量統計根據「<a href="https://openchat.line.me/jp" rel="external" target="_blank">LINE 開放聊天室官方網站</a>」的數據，製作圖表和排名並展示於網站上。
                </p>
                <p>
                    為了顯示最新數據，LINE社群流量統計的<a href="https://webtan.impress.co.jp/g/%E3%82%AF%E3%83%AD%E3%83%BC%E3%83%A9%E3%83%BC" target="_blank">爬蟲程式</a>定期巡覽官方網站並將開放聊天室的數據進行索引。
                </p>
                <p>
                    <b>數據僅從官方網站獲取，不會取得與 LINE 應用程式本體相關的數據。</b>
                </p>
                <section style="margin: 1rem 0;">
                    <h3>LINE社群流量統計收錄的條件</h3>
                    <p>
                        LINE社群流量統計的爬蟲程式從<a href="https://openchat.line.me/jp/explore?sort=RANKING" rel="external" target="_blank">官方網站排名</a>中找到新的聊天室並進行登記。<b>未列入排名的聊天室不會被登記。</b>
                    </p>
                    <p>
                        一旦登記到LINE社群流量統計的聊天室，無論是否列入官方排名，仍會繼續在LINE社群流量統計中展示。
                    </p>
                    <p>
                        如果官方網站中聊天室的展示已結束或被刪除，該聊天室也會從LINE社群流量統計中刪除。
                    </p>
                    <p>
                        未列入排名（例如剛建立）的聊天室，只要出現在官方網站中，也可以手動登記到LINE社群流量統計。
                    </p>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>資訊更新的排程</h3>
                    <p>
                        LINE社群流量統計的爬蟲程式定期巡覽官方網站，更新聊天室的標題、說明、圖片、人數統計及排名歷史等資訊。
                    </p>
                    <ul style="font-size: 18px; line-height: 2;">
                        <li>排名中的聊天室：每小時更新</li>
                        <li>未列入排名的聊天室：每天更新（23:30 ～ 0:30）</li>
                        <li>未列入排名且人數超過一週未變動的聊天室：每週更新一次</li>
                    </ul>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>關於排名的趨勢圖</h3>
                    <p>
                        LINE社群流量統計的爬蟲程式每小時記錄官方網站中的排名位置，透過聊天室的排列順序計算排名。
                    </p>
                    <p>
                        未列入排名的聊天室會記錄為「圏外」。聊天室管理員更新聊天室資訊（標題、說明、圖片）後，或因伺服器錯誤等原因，也可能出現「圏外」的情況。
                    </p>
                </section>
            <?php elseif (\Shared\MimimalCmsConfig::$urlRoot === '/th') : ?>
                <h1 style="letter-spacing: 0px;">เกี่ยวกับ LINE OPENCHAT สถิติการเข้าชม</h1>
                <p>LINE OPENCHAT สถิติการเข้าชม เป็นเว็บไซต์ที่ช่วยให้ผู้ใช้ค้นหา โอเพนแชท และเปรียบเทียบแนวโน้มการเติบโตผ่านกราฟและการจัดอันดับ</p>
                <p>ดำเนินการโดย<a href="https://github.com/pika-0203/Open-Chat-Graph" target="_blank">โครงการโอเพนซอร์ส</a> (ไม่ใช่ทางการของ LINE)</p>
                <h2>เป้าหมายของเว็บไซต์</h2>
                <p>・สร้างโอกาสให้ผู้ใช้ค้นหาและเข้าร่วม โอเพนแชท</p>
                <p>・ช่วยให้ผู้ดูแล โอเพนแชท เข้าใจแนวโน้มการเติบโตและเปรียบเทียบข้อมูลเพื่อสนับสนุนการจัดการ</p>

                <h2>ระบบการรวบรวมข้อมูล โอเพนแชท</h2>
                <p>
                    LINE OPENCHAT สถิติการเข้าชม สร้างกราฟและการจัดอันดับโดยใช้ข้อมูลจาก「<a href="https://openchat.line.me/jp" rel="external" target="_blank">เว็บไซต์ทางการของ LINE OpenChat</a>」
                </p>
                <p>
                    เพื่อแสดงข้อมูลล่าสุด LINE OPENCHAT สถิติการเข้าชม ใช้<a href="https://webtan.impress.co.jp/g/%E3%82%AF%E3%83%AD%E3%83%BC%E3%83%A9%E3%83%BC" target="_blank">โปรแกรมรวบรวมข้อมูล (Crawler)</a> ในการเยี่ยมชมเว็บไซต์ทางการเป็นประจำและบันทึกข้อมูล โอเพนแชท
                </p>
                <p>
                    <b>ข้อมูลจะถูกรวบรวมจากเว็บไซต์ทางการเท่านั้น จะไม่มีการรวบรวมข้อมูลจากตัวแอปพลิเคชัน LINE</b>
                </p>
                <section style="margin: 1rem 0;">
                    <h3>เงื่อนไขการรวบรวมข้อมูลใน LINE OPENCHAT สถิติการเข้าชม</h3>
                    <p>
                        โปรแกรมรวบรวมข้อมูลของ LINE OPENCHAT สถิติการเข้าชม ค้นหาและลงทะเบียนห้องใหม่จาก<a href="https://openchat.line.me/jp/explore?sort=RANKING" rel="external" target="_blank">การจัดอันดับบนเว็บไซต์ทางการ</a> <b>ห้องที่ไม่ได้อยู่ในการจัดอันดับจะไม่ได้รับการลงทะเบียน</b>
                    </p>
                    <p>
                        ห้องที่ได้รับการลงทะเบียนใน LINE OPENCHAT สถิติการเข้าชม แล้ว จะยังคงแสดงอยู่ใน LINE OPENCHAT สถิติการเข้าชม แม้ว่าจะไม่มีในอันดับ
                    </p>
                    <p>
                        หากห้องถูกลบหรือหยุดแสดงในเว็บไซต์ทางการ ห้องดังกล่าวจะถูกลบออกจาก LINE OPENCHAT สถิติการเข้าชม ด้วย
                    </p>
                    <p>
                        ห้องที่ไม่ได้อยู่ในการจัดอันดับ (เช่น ห้องที่เพิ่งสร้าง) สามารถลงทะเบียนใน LINE OPENCHAT สถิติการเข้าชม ได้ด้วยตนเองหากอยู่ในเว็บไซต์ทางการ
                    </p>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>กำหนดการอัปเดตข้อมูล</h3>
                    <p>
                        โปรแกรมรวบรวมข้อมูลของ LINE OPENCHAT สถิติการเข้าชม เยี่ยมชมเว็บไซต์ทางการเป็นประจำเพื่ออัปเดตข้อมูล เช่น ชื่อห้อง คำอธิบาย รูปภาพ สถิติสมาชิก ประวัติการจัดอันดับ ฯลฯ
                    </p>
                    <ul style="font-size: 18px; line-height: 2;">
                        <li>ห้องที่อยู่ในการจัดอันดับ: อัปเดตทุกชั่วโมง (ประมาณเวลา 30 นาทีของทุกชั่วโมง)</li>
                        <li>ห้องที่ไม่ได้อยู่ในการจัดอันดับ: อัปเดตทุกวัน (23:30 ～ 0:30)</li>
                        <li>ห้องที่ไม่ได้อยู่ในการจัดอันดับและไม่มีการเปลี่ยนแปลงจำนวนสมาชิกเกิน 1 สัปดาห์: อัปเดตทุกสัปดาห์</li>
                    </ul>
                </section>
                <section style="margin: 1rem 0;">
                    <h3>เกี่ยวกับกราฟอันดับการจัดอันดับ</h3>
                    <p>
                        โปรแกรมรวบรวมข้อมูลของ LINE OPENCHAT สถิติการเข้าชม บันทึกอันดับการจัดอันดับบนเว็บไซต์ทางการทุกชั่วโมง โดยคำนวณอันดับจากลำดับของห้อง
                    </p>
                    <p>
                        ห้องที่ไม่มีในอันดับจะถูกบันทึกเป็น "นอกอันดับ" อาจเกิดกรณี "นอกอันดับ" หลังการอัปเดตข้อมูลห้อง (ชื่อ คำอธิบาย รูปภาพ) โดยผู้ดูแลห้อง หรือเกิดจากข้อผิดพลาดของเซิร์ฟเวอร์
                    </p>
                </section>
            <?php endif ?>
        </article>
    </main>
    <?php \App\Views\Ads\GoogleAdsense::loadAdsTag() ?>
    <?php viewComponent('footer_inner') ?>
    <?php echo $_breadcrumbsShema ?>
</body>

</html>