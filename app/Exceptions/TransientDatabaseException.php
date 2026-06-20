<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * 一過性のDB障害でリトライを尽くしたときに DB 層が投げるドメイン例外。
 *
 * 「一過性」= アクセス集中・サーバ瞬断・ロック輻輳などで一時的に処理できないが、
 * 時間をおけば回復が見込めるもの。具体的には:
 *   - MySQL: 接続数上限(max_user_connections 1226 / Too many connections 1040)、
 *     サーバ瞬断(server has gone away 2006 / Lost connection 2013)、接続不可(2002)
 *   - SQLite: ロック輻輳(database is locked / locking protocol)
 * 本物の不具合(SQL構文エラー・テーブル/カラム不在・SQLite corrupt/readonly 等)は含めない。
 *
 * この例外は「HTTP 503」ではなく「DB が一時的に駄目だった」というドメインの事実だけを表す。
 * HTTP ステータスへの対応付けや通知方法の出し分け(Web は 503 + 10件バッチ通知 / CLI は即時通知)は
 * 上位の App\Exceptions\Handlers\ApplicationExceptionHandler が担う。DB 層は SAPI も HTTP も知らない。
 *
 * リトライ判定(DB::isConnectionException 等)が getPrevious を辿れるよう、元の \PDOException は
 * $previous に連結する。検出と本例外への変換は App\Models\Repositories\DB(MySQL) と
 * App\Models\SQLite\AbstractSQLite(SQLite) が行い、フレームワーク本体(shadow/・shared/MimimalCMS_*.php)
 * には手を入れない。
 */
class TransientDatabaseException extends \RuntimeException
{
}
