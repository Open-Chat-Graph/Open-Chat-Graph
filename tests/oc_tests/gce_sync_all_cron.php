<?php

use App\Models\GCE\DBGce;
use App\Models\GCE\GceDbTableSynchronizer;
use App\Models\GCE\GceRankingUpdater;
use App\Services\Admin\AdminTool;

require_once __DIR__ . '/vendor/autoload.php';

set_time_limit(3600 * 4);
ini_set('memory_limit', '4G');


function gcesyncall(GceDbTableSynchronizer $sql, GceRankingUpdater $gce)
{
    $message = "start: " . date('Y-m-d H:i:s');
    AdminTool::sendLineNofity($message);

    DBGce::execute("TRUNCATE TABLE open_chat");
    $result = $sql->syncOpenChatAll();
    $message = 'syncOpenChatAll: ' . $result . "\nend: " . date('Y-m-d H:i:s');
    AdminTool::sendLineNofity($message);
    
    $gce->updateRanking();

    AdminTool::sendLineNofity($message);
}

try {
    gcesyncall(app(GceDbTableSynchronizer::class), app(GceRankingUpdater::class));
} catch (\Throwable $e) {
    AdminTool::sendLineNofity($e->__toString());
}
