<!DOCTYPE html>
<html lang="jp">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo fileUrl("style/mvp.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_header.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/site_footer.css", urlRoot: '') ?>">
    <link rel="stylesheet" href="<?php echo fileUrl("style/ads_element.css", urlRoot: '') ?>">
    <title>アフィリエイト広告</title>
</head>

<body>
    <style>
        .kokoku-preview {
            background: rgb(240, 240, 240);
        }

        .kokoku-preview .kokoku-box {
            background: #fff;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .kokoku-preview .kokoku-element {
            margin: auto;
            max-width: 812px;
        }

        .copy-mode {

            #copy_btn,
            #delete_form,
            #updated_at,
            #id_legend {
                display: none;
            }
        }

        @media screen and (max-width: 511px) {
            .ads-sample .kokoku-element {
                margin: 1rem -1rem;
                width: unset;
            }
        }
    </style>
    <?php viewComponent('site_header') ?>
    <div class="kokoku-preview" style="padding: 1rem 0; width: 100%; margin: auto">
        <?php $dto->echoAdsElement() ?>
    </div>
    <main style="padding-top: 0;">
        <section style="max-width: 812px; margin: auto;">

            <div id="form_outer" style="position: relative; width: 100%;">
                <form onsubmit="return confirm('送信しますか？')" id="edit-form" method="POST" action="/ads/<?php echo $dto->id === 0 ? 'register' : 'update' ?>" style="width: 100%;">

                    <?php foreach ($dto as $el => $value) : ?>

                        <?php if ($el === 'id') : ?>
                            <?php if ($value) : ?>
                                <input type="hidden" name="id" value="<?php echo $value ?>">
                                <legend id="id_legend">id: <?php echo $value ?></legend>
                            <?php endif ?>
                            <?php continue ?>
                        <?php endif ?>

                        <?php if ($el === 'updated_at') : ?>
                            <legend id="updated_at">updated_at: <?php echo $value ?></legend>
                            <?php continue ?>
                        <?php endif ?>

                        <label for="<?php echo $el ?>"><?php echo $el ?></label>
                        <textarea id="<?php echo $el ?>" name="<?php echo $el ?>" <?php if ($el !== 'ads_paragraph' || $el !== 'ads_tracking_url') echo 'required' ?>><?php echo $value ?></textarea>

                    <?php endforeach ?>

                    <input type="submit">
                    <?php if ($dto->id > 0) : ?>
                        <button id="copy_btn" style="margin-left: 1rem;" type="button">コピー</button>
                    <?php endif ?>
                </form>

                <?php if ($dto->id > 0) : ?>
                    <form id="delete_form" onsubmit="return confirm('削除しますか？')" method="POST" action="/ads/delete" style="border: unset; box-shadow: unset; position: absolute; bottom: 0; right: 0;">
                        <input type="hidden" value="<?php echo $dto->id ?>" name="id">
                        <input type="submit" value="削除" style="background-color: #ff5d6d; border-color: #ff5d6d; padding: 4px;">
                    </form>
                <?php endif ?>
            </div>

            <p style="display: flex; gap:1rem;">
                <a href="<?php echo url('ads') ?>">新規作成</a>
                <a href="<?php echo url('labs/tags/ads') ?>">タグ</a>
            </p>
        </section>

        <section class="ads-sample">
            <?php foreach ($dtoArray as $adsDto) : ?>
                <div style="max-width: 400px; position: relative;">
                    <a href="/ads?id=<?php echo $adsDto->id ?>" style="all: unset; display: block; cursor: pointer; position:absolute; width:100%; height:100%; z-index:1;">
                    </a>
                    <p style="text-align: center; font-size:13px; color:#777;">id:<?php echo $adsDto->id ?>, updated_at:<?php echo $adsDto->updated_at ?></p>
                    <?php $adsDto->echoAdsElement() ?>
                </div>
            <?php endforeach ?>
        </section>
    </main>
    <footer>
        <?php viewComponent('footer_inner') ?>
    </footer>
    <script defer src="<?php echo fileUrl("/js/site_header_footer.js", urlRoot: '') ?>"></script>
    <script>
        const form = document.getElementById('edit-form')

        document.getElementById('copy_btn')?.addEventListener('click', () => {
            if (!confirm('コピーして新規作成しますか？'))
                return
            form.action = '/ads/register'
            document.getElementById('form_outer').classList.add('copy-mode')
        })

        const preview = document.querySelector('.kokoku-preview')

        <?php foreach (array_keys(get_object_vars($dto)) as $el) : ?>

            <?php if ($el === 'id') continue ?>
            <?php if ($el === 'updated_at') continue ?>

            <?php if ($el === 'ads_img_url') : ?>
                const <?php echo $el ?> = preview.querySelector('.<?php echo $el ?>')
                document.getElementById('<?php echo $el ?>').addEventListener('input', (e) => {
                    <?php echo $el ?>.src = e.target.value
                })
                <?php continue ?>
            <?php endif ?>

            <?php if ($el === 'ads_href') : ?>
                const <?php echo $el ?> = preview.querySelector('.<?php echo $el ?>')
                document.getElementById('<?php echo $el ?>').addEventListener('input', (e) => {
                    <?php echo $el ?>.href = e.target.value
                })
                <?php continue ?>
            <?php endif ?>

            const <?php echo $el ?> = preview.querySelector('.<?php echo $el ?>')
            document.getElementById('<?php echo $el ?>').addEventListener('input', (e) => {
                <?php echo $el ?>.textContent = e.target.value
            })

        <?php endforeach ?>
    </script>
</body>

</html>