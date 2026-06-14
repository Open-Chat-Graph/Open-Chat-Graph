<?php

declare(strict_types=1);

use App\Services\Recommend\Dto\RecommendListDto;
use App\Services\Recommend\Enum\RecommendListType;
use PHPUnit\Framework\TestCase;

/** DTOのserializeラウンドトリップ と 旧形式(.dat)互換(デプロイ安全性) の検証 */
class RecommendListDtoTest extends TestCase
{
    private function row(int $id, int $member): array
    {
        return ['id' => $id, 'name' => "room{$id}", 'img_url' => '', 'member' => $member,
            'emblem' => 0, 'api_created_at' => 0, 'join_method_type' => 0,
            'table_name' => 'open_chat', 'diff_member_24h' => null, 'desc40' => ''];
    }

    public function testNewRoundTrip(): void
    {
        $rows = [];
        for ($i = 1; $i <= 50; $i++) $rows[] = $this->row($i, 1000 - $i * 10);
        $dto = new RecommendListDto(RecommendListType::Tag, 'tag', $rows, '2026-06-14 10:30:00');

        $restored = unserialize(serialize($dto));
        $this->assertSame(30, $restored->getCount(), '表示は先頭30件');
        $this->assertSame(array_column(array_slice($rows, 0, 30), 'id'), array_column($restored->getList(false, 30), 'id'));
        // プールはfindByMemberRangeで全50件から絞れる
        $near = $restored->findByMemberRange(0, 500, 0, 1000, 5);
        $this->assertNotEmpty($near);
    }

    public function testOldDatBackwardCompat(): void
    {
        // 旧形式DTO: hour/day/week/member プロパティを持ち、list を持たない serialize 文字列を手組みする。
        $hour = [$this->row(1, 900), $this->row(2, 800)];
        $member = [$this->row(3, 700)];
        $merged = array_merge($hour, $member);
        $props = [
            'type' => RecommendListType::Tag,
            'listName' => 'oldtag',
            'hour' => $hour,
            'day' => [],
            'week' => [],
            'member' => $member,
            'hourlyUpdatedAt' => '2026-06-14 09:30:00',
            'maxMemberCount' => 900,
            'mergedElements' => $merged,
            'shuffledMergedElements' => null,
            'sortAndUniqueTags' => [],
            'themeMomentum' => [],
            'relatedTags' => [],
        ];
        $parts = '';
        $n = 0;
        foreach ($props as $k => $v) {
            $parts .= serialize($k) . serialize($v);
            $n++;
        }
        $cls = RecommendListDto::class;
        $old = sprintf('O:%d:"%s":%d:{%s}', strlen($cls), $cls, $n, $parts);

        // E_ALL例外化環境でも fatal/Deprecated例外なく unserialize できること
        $dto = unserialize($old);
        $this->assertInstanceOf(RecommendListDto::class, $dto);
        // 表示(mergedElements)は旧.datの値で生きる
        $this->assertSame([1, 2, 3], array_column($dto->getList(false, 30), 'id'));
        $this->assertSame(3, $dto->getCount());
        // listは未設定→デフォルト[]で findByMemberRange が落ちない（空を返すだけ）
        $this->assertSame([], $dto->findByMemberRange(0, 800, 0, 9999, 5));
    }
}
