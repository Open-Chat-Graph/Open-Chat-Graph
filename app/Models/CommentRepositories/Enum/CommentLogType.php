<?php

declare(strict_types=1);

namespace App\Models\CommentRepositories\Enum;

enum CommentLogType: string
{
    case AddComment = 'AddComment';
    case Report = 'Report';
    case AddLike = 'AddLike';
    case DeleteLike = 'DeleteLike';
    case ImageReport = 'ImageReport';
    case AdminDelete = 'AdminDelete';
    case AdminRestore = 'AdminRestore';
    case AdminBanUser = 'AdminBanUser';
    case AdminBulkDelete = 'AdminBulkDelete';
    case AdminBulkRestore = 'AdminBulkRestore';

    /** admin系typeかどうか */
    public function isAdmin(): bool
    {
        return match ($this) {
            self::AdminDelete, self::AdminRestore, self::AdminBanUser,
            self::AdminBulkDelete, self::AdminBulkRestore => true,
            default => false,
        };
    }

    /** admin系typeのvalue一覧（SQL IN句用） */
    public static function adminTypes(): array
    {
        return [
            self::AdminDelete->value,
            self::AdminRestore->value,
            self::AdminBanUser->value,
            self::AdminBulkDelete->value,
            self::AdminBulkRestore->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminDelete => 'フラグ変更',
            self::AdminRestore => '復元',
            self::AdminBanUser => 'シャドウバン',
            self::AdminBulkDelete => '一括削除',
            self::AdminBulkRestore => '一括復元',
            default => $this->value,
        };
    }

    /**
     * admin系typeのラベルにコメントのflag情報を付加して返す
     * @param int|null $commentFlag コメントの現在のflag値
     */
    public function adminLabel(?int $commentFlag): string
    {
        $base = $this->label();
        if ($commentFlag === null || !$this->isAdmin()) {
            return $base;
        }

        $flagLabel = \App\Config\AppConfig::COMMENT_FLAG_LABELS[$commentFlag] ?? "flag={$commentFlag}";
        return match ($this) {
            self::AdminDelete, self::AdminBulkDelete => "{$base}({$flagLabel})",
            default => $base,
        };
    }
}