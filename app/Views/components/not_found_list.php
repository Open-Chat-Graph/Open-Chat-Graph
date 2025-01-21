<main style="padding-top: 0; padding-bottom: 0;">
    <?php

    use App\Views\Ads\GoogleAdsence as GAd; ?>

    <?php viewComponent('top_ranking_comment_list_hour', compact('dto')) ?>
    <hr class="hr-bottom">
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>
    <hr class="hr-top">
    <?php viewComponent('top_ranking_comment_list_hour24', compact('dto')) ?>
    <hr class="hr-bottom">
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>
    <hr class="hr-top">
    <?php viewComponent('top_ranking_comment_list_member', compact('dto')) ?>
    <hr class="hr-bottom">
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>
    <hr class="hr-top">
    <?php viewComponent('top_ranking_comment_list_week', compact('dto')) ?>
    <hr class="hr-bottom">
    <?php GAd::output(GAd::AD_SLOTS['siteSeparatorRectangle']) ?>
    <?php GAd::loadAdsTag() ?>
</main>