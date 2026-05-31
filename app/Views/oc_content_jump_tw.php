<?php

/** 加入前確認頁面（繁體中文）。版面集中於 components/oc_jump_page
 *  禁止事項來源: LINE 台灣官方部落格「LINE 社群使用規範」 */

$htmlLang = t('ja'); // translation.json: ja => zh-TW

$txt = [
  'noticeTitle' => '加入前的確認',
  'noticeLead'  => '請先確認此開放式聊天的說明內容與 LINE 的各項禁止事項，再加入。',
  'aboutLabel'  => '關於此開放式聊天',
  'rulesTitle'  => 'LINE 社群（開放式聊天）禁止事項',
  'rulesLead'   => '請於點擊「以LINE開啟」按鈕前，先閱讀以下各項禁止事項。',
  'sourceLabel' => '資料來源：',
  'sourceText'  => 'LINE 台灣官方部落格・LINE 社群使用規範',
  'sourceUrl'   => 'https://line-tw-official.weblog.to/archives/82859412.html',
  'openButton'  => t('LINEで開く'),
];

$rules = [
  ['title' => '禁止揭露個人 LINE ID', 'desc' => '禁止在聊天內容中揭露個人 LINE ID（如果是官方帳號的 ID 則沒關係）。'],
  ['title' => '禁止單獨會面相關對話', 'desc' => '禁止在 LINE 社群中溝通與「有單獨會面意圖」的所有對話。'],
  ['title' => '禁止直銷相關討論', 'desc' => '禁止傳直銷相關討論。'],
  ['title' => '禁止有害兒少內容', 'desc' => '禁止色情、暴力、血腥、恐怖等「有害兒少身心健康」相關討論及內容。'],
  ['title' => '菸酒及管制物品討論應符合法令', 'desc' => '菸（包括雪茄、加熱菸、類菸品如電子菸等）、酒類，或法令禁止或列管兒少接觸物品之討論，應符合法令。'],
  ['title' => '禁止博弈與投注', 'desc' => '禁止博弈（包括麻將、撲克）、運動賽事投注相關討論。'],
  ['title' => '禁止違法行為', 'desc' => '禁止任何違法行為（包括禁止販售：仿冒品、活體寵物、處方箋藥物…等，任何違法行為）。'],
];

viewComponent('oc_jump_page', compact('_meta', '_css', 'oc', 'txt', 'rules', 'htmlLang'));
