<?php

/**
 * 関連ルーム(類似サイズ or おすすめ)セクション。
 * /oc 本体では描画せず、非同期 deferred-sections エンドポイント経由で生成・注入する
 * （高負荷対策: bot が叩く /oc 本体から recommend(最大6 MySQLクエリ)/similarSize を外すため）。
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
<?php elseif ($recommend[3]) : ?>
  <aside class="recommend-list-aside" id="recommend-list-aside1">
    <?php viewComponent('recommend_list2', ['recommend' => $recommend[3], 'member' => $oc['member'], 'tag' => '', 'id' => $oc['id']]) ?>
  </aside>
<?php endif ?>
