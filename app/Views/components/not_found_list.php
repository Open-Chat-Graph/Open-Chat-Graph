<main style="padding-top: 0; padding-bottom: 0;">
    <?php

    use App\Views\Ads\GoogleAdsense as GAd; ?>

    <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>

    <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>

    <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>

    <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>

</main>