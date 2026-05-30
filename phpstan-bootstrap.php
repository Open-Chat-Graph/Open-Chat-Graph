<?php

// PHPStan用autoloader
// Composerのautoloaderを読み込んだ後、カスタムエラーハンドラを無効化する
// （MimimalCMS_ExceptionHandler.phpがComposer autoloadで登録するハンドラが
//   PHPStan内部のinclude警告をErrorExceptionに変換してしまうため）
require_once __DIR__ . '/vendor/autoload.php';
restore_error_handler();
