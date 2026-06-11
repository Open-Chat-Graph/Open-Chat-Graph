<?php

/**
 * 関連ルーム(類似サイズ or おすすめ)セクション。
 * /oc 表示時に recommend 静的キャッシュ(.dat / 母集団300件)から都度組み立てて描画する（MySQL不使用）。
 *
 * @var array|false $similarSize
 * @var array{0:mixed,1:mixed,2:string,3:mixed} $recommend
 * @var array $oc
 */
?>
<?php if (isset($similarSize) && $similarSize) : ?>
  <aside class="recommend-list-aside" id="similar-size-rooms">
    <?php viewComponent('similar_size_rooms', [
      'rooms'         => $similarSize['rooms'],
      'recommend'     => $similarSize['recommend'],
      'mode'          => $similarSize['mode'],
      'currentMember' => $oc['member'],
      'category'      => $oc['category'] ?? null,
      'tag'           => $oc['tag1'] ?? null,
    ]) ?>
  </aside>
<?php elseif (!empty($recommend[3])) : ?>
  <aside class="recommend-list-aside" id="recommend-list-aside1">
    <?php viewComponent('recommend_list2', ['recommend' => $recommend[3], 'member' => $oc['member'], 'tag' => '', 'id' => $oc['id']]) ?>
  </aside>
<?php endif ?>
