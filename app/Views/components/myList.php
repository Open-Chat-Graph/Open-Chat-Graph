<article class="mylist pad-side">
    <div class="refresh-time openchat-list-date">
        <span style="font-weight: bold; color:#111; font-size:13px; margin: 0; line-height: unset;">ピン留め (24時間の人数増加)</span>
        <span style="font-weight: normal; color:#aaa; font-size:13px; margin: 0; line-height: unset;"><?php echo $hourlyUpdatedAt->format('G:i') ?></span>
    </div>
    <div style="margin: -4px 0 -4px 0;">
        <?php viewComponent('open_chat_list_ranking', ['openChatList' => $myList, 'isHourly' => true, 'noReverse' => true]) ?>
    </div>
</article>