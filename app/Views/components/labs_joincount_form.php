<style>
    .form-section {
        margin: 2rem 1rem;
    }

    .form-section form {
        font-size: 13px;
        width: 100%;
        box-shadow: none;
        border: 1px solid var(--c-border-plain-2);
    }

    #file-input {
        width: 100%;
        border: 1px solid var(--c-border-plain-2);
    }

    .form-section form p {
        font-weight: bold;
        font-size: 15px;
    }

    .form-section .small-font {
        font-size: 13px;
    }

    .form-section .form-errorMessage {
        color: var(--c-red-soft);
        font-weight: bold;
    }
</style>
<section class="form-section">
    <form>
        <p>参加人数の推移を読み込む</p>
        <legend class="small-font">テキスト形式のトーク履歴を選択</legend>
        <input id="file-input" type="file" accept="text/plain">
        <p class="form-errorMessage" id="errorMessage"></p>
        <small>端末上で読み込むため、トーク履歴が当サイトに送信されることはありません。</small>
    </form>
</section>