<?php

declare(strict_types=1);

namespace App\Services\Recommend;

use App\Models\Repositories\SyncOpenChatStateRepositoryInterface;
use App\Services\Admin\AdminTool;
use App\Services\Cron\Enum\BatchScript;
use App\Services\Cron\Enum\SyncOpenChatStateType as StateType;
use App\Services\Cron\Utility\BatchScriptLauncher;
use App\Services\Cron\Utility\CronUtility;

/**
 * おすすめタグの全レコード再適用（rebuildAllViaShadowSwap / updateRecommendTables(false)）の
 * クロススクリプト排他ロック。
 *
 * 排他したいのは毎時CRON（update_recommend_static_data.php）とGUI再適用（tag_update.php）の
 * 2経路が同時に全件再構築（同じ shadow テーブル）を走らせないこと。
 *
 * 単純な真偽フラグ（isRecommendTagRebuildActive）だけだと、ロック保持プロセスが多重起動解消の
 * kill などで SIGKILL されたとき finally が走らずフラグが永久に立ち残り、以後の全再適用が恒久的に
 * スキップされる（= 定義を変えても古い部屋が付け直されない）。実際に本番でこの状態に陥った。
 *
 * そこで「フラグが立っていても所有プロセスが生存していなければ漏れたロックとみなして回収する」
 * というプロセス死活ベースの自己修復を行う。ps 誤検知に備えて、規定時間を超えて保持され続けた
 * ロックも保険として強制回収する。
 */
class RecommendTagRebuildLock
{
    /**
     * 所有プロセスの生存判定が誤検知（defunct 等）でも、これを超えて立ち続けるロックは
     * 漏れたものとみなして強制回収する保険値（秒）。実再構築は分オーダーで終わるため十分大きく取る。
     */
    private const MAX_HELD_SECONDS = 7200;

    public function __construct(
        private SyncOpenChatStateRepositoryInterface $state,
        private BatchScriptLauncher $launcher,
    ) {}

    /**
     * 全再適用が「実際に生きているプロセスに」保持されているか。
     *
     * フラグが立っていても、所有プロセス（tag_update.php / update_recommend_static_data.php）が
     * 居ない、または規定時間を超過している場合は、漏れたロックとみなしてその場で回収（フラグを
     * 下ろす）し、false を返す。これにより呼び出し側は新たに再適用を握れる。
     */
    public function isHeldByLiveProcess(): bool
    {
        if (!$this->state->getBool(StateType::isRecommendTagRebuildActive)) {
            return false;
        }

        if ($this->hasLiveOwnerProcess() && !$this->hasExceededMaxHeldTime()) {
            return true;
        }

        $startedAt = $this->state->getString(StateType::recommendTagRebuildStartedAt);
        $message = '漏れたタグ全再適用ロックを検知したため回収します'
            . '（所有プロセス不在 または 規定時間超過, startedAt=' . ($startedAt !== '' ? $startedAt : 'unknown') . '）';
        CronUtility::addCronLog($message);
        AdminTool::sendDiscordNotify($message);
        $this->release();

        return false;
    }

    /** ロックを取得する（フラグを立て、取得時刻を記録する）。 */
    public function acquire(): void
    {
        $this->state->setString(StateType::recommendTagRebuildStartedAt, date('Y-m-d H:i:s'));
        $this->state->setTrue(StateType::isRecommendTagRebuildActive);
    }

    /** ロックを解放する（フラグを下ろす）。 */
    public function release(): void
    {
        $this->state->setFalse(StateType::isRecommendTagRebuildActive);
    }

    private function hasLiveOwnerProcess(): bool
    {
        return $this->launcher->isAnyInstanceRunning(BatchScript::updateRecommendStaticData)
            || $this->launcher->isAnyInstanceRunning(BatchScript::tagUpdate);
    }

    private function hasExceededMaxHeldTime(): bool
    {
        $startedAt = $this->state->getString(StateType::recommendTagRebuildStartedAt);
        if ($startedAt === '') {
            return true; // 取得時刻が無い＝旧形式や記録漏れ → 回収対象とみなす
        }

        $startedTs = strtotime($startedAt);
        if ($startedTs === false) {
            return true;
        }

        return (time() - $startedTs) > self::MAX_HELD_SECONDS;
    }
}
