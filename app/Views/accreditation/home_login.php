<?php

use App\Views\Content\Accreditation\AccreditationAdminViewContent;

$returnTo = "?return_to=/accreditation/bronze/home";
?>

<!DOCTYPE html>
<html lang="ja">
<?php AccreditationAdminViewContent::headComponent(null, true) ?>

<body>
    <style>
        a:hover {
            border-bottom: 0;
            text-decoration: underline;
        }

        .logo {
            filter: invert(26%) sepia(10%) saturate(21%) hue-rotate(316deg) brightness(98%) contrast(87%);
            max-width: 250px;
        }
    </style>
    <header>
        <img class="logo" src="<?php echo fileUrl('assets/accreditation-log.svg') ?>" alt="オプチャ検定" />
        <h1>問題投稿ページ</h1>
    </header>
    <main>
        <article>
            <p>
                問題投稿ページでは誰でも問題を投稿でき、他の人が投稿した問題を見ることができます。<br>
                投稿した問題は１問のクイズとしてGoogle検索などに表示され、投稿者の名前やオプチャのリンクを表示することができます。
            </p>
            <a style="display: block; width:fit-content;" href="/auth/login<?php echo $returnTo ?>">
                <img style="display: block; width: 240px;" src="<?php echo fileUrl('assets/line_login_btn.png') ?>" alt="LINEでログイン">
            </a>
            <hr>
            <h2>オプチャ検定とは</h2>
            <p>
                オプチャ検定は、LINEオープンチャットの利用に関連する知識を深める場所です。<br>
                ガイドライン、ルール、管理方法などについて楽しく学ぶことができます。
            </p>
            <h2>検定って何？</h2>
            <p>
                検定は、たくさんの問題からランダムで出題されるものを制限時間内に答えて、得点によって合格・不合格が決まるものです。
            </p>
            <p>
                <a href="/accreditation" target="_blank">オプチャ検定｜公式サイト</a>
                <br>
                オプチャ検定の問題が出題されているページ
            </p>
            <p>
                一定の基準のもとに出題されている問題から、クイズ形式で正解・不正解が分かります。
                <br>
                これによってオープンチャットの正しいルールを確かめる事ができ、ゲーム感覚で学ぶことができます。
            </p>
            <p>
                時間が経つにつれ、投稿された問題が増えていき、出題のパターンが広がります。
            </p>
            <h2>問題投稿ページは何をするところ？</h2>
            <p>
                オプチャ検定の問題投稿ページでは、オプチャ検定の問題集を管理しています。
                <br>
                LINEログインでメンバー登録を行い、誰でも新しい問題を投稿することができます。
            </p>
            <p>
                問題が検定に出題された際は、投稿者のニックネーム・オプチャ名・オプチャリンクが問題の解説欄に掲載されます。
            </p>
            <hr>
            <h2>LINEログインについて</h2>
            <p>
                オプチャ検定の問題投稿ページでは、メンバー登録のためにLINEログインを利用しています。<br>
            </p>
            <p>
                LINEに登録している名前やプロフィール画像などが表示されることはなく、お好きなニックネームを設定できます。<br>
                また、料金が発生することはなく、無料でご利用いただけます。<br>
            </p>
            <p>
                当サイトではLINEに登録している個人情報などを取得しないため、安心してご利用いただけます。<br>
                LINEアプリに当サイトから通知が来ることもありません。<br>
            </p>
        </article>
    </main>
    <footer>
        <hr style="margin-top: 0rem;">
        <p>
            このサイトはLINEオープンチャット非公式です。LINEヤフー社はこの内容に関与していません。<br>監修しているのは一部のLINEオープンチャット公認メンターです。
        </p>
        <a href="/accreditation/privacy" style="color: #b7b7b7; text-decoration: none;">プライバシーポリシー</a>
        <a href="/policy/term" style="color: #b7b7b7; text-decoration: none; margin-left: 1rem;">利用規約</a>
    </footer>
    <?php /** @var \App\Services\Accreditation\QuizApi\Dto\Topic $_argDto */
    echo \App\Services\Accreditation\AccreditationSchemaGenerator::breadcrumbList(
        '問題を投稿',
        'login',
    ) ?>
</body>

</html>