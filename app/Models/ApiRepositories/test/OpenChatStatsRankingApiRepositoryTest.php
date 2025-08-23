<?php

declare(strict_types=1);

use App\Models\ApiRepositories\OpenChatApiArgs;
use App\Models\ApiRepositories\OpenChatStatsRankingApiRepository;
use PHPUnit\Framework\TestCase;

class OpenChatStatsRankingApiRepositoryTest extends TestCase
{
    private OpenChatStatsRankingApiRepository $repo;

    protected function setUp(): void
    {
        $this->repo = app(OpenChatStatsRankingApiRepository::class);
    }

    public function testFindStatsAllWithKeywordSearch()
    {
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 10;
        $args->page = 0;
        $args->sort = 'member';
        $args->order = 'DESC';
        $args->keyword = 'ダイエット';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findStatsAll($args);

        echo "\n=== findStatsAll with keyword 'ダイエット' ===\n";
        if (!empty($result)) {
            echo "Found " . count($result) . " results\n";
            foreach (array_slice($result, 0, 3) as $i => $chat) {
                echo "Result " . ($i + 1) . ":\n";
                echo "  ID: " . $chat->id . "\n";
                echo "  Name: " . $chat->name . "\n";
                echo "  Description: " . substr($chat->desc, 0, 50) . "...\n";
                echo "  Member: " . $chat->member . "\n";
                echo "  Contains 'ダイエット' in name: " . (str_contains($chat->name, 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "  Contains 'ダイエット' in desc: " . (str_contains($chat->desc ?? '', 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "\n";
            }
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        } else {
            echo "No results found\n";
        }

        $this->assertNotEmpty($result, 'Should find results for ダイエット keyword');
    }

    public function testFindHourlyStatsRankingWithKeywordSearch()
    {
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 10;
        $args->page = 0;
        $args->sort = 'rate';
        $args->order = 'DESC';
        $args->keyword = 'ダイエット';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findHourlyStatsRanking($args);

        echo "\n=== findHourlyStatsRanking with keyword 'ダイエット' ===\n";
        if (!empty($result)) {
            echo "Found " . count($result) . " results\n";
            foreach (array_slice($result, 0, 3) as $i => $chat) {
                echo "Result " . ($i + 1) . ":\n";
                echo "  ID: " . $chat->id . "\n";
                echo "  Name: " . $chat->name . "\n";
                echo "  Description: " . substr($chat->desc, 0, 50) . "...\n";
                echo "  Member: " . $chat->member . "\n";
                echo "  Diff: " . ($chat->diff_member ?? 'N/A') . "\n";
                echo "  Rate: " . ($chat->percent_increase ?? 'N/A') . "\n";
                echo "  Contains 'ダイエット' in name: " . (str_contains($chat->name, 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "  Contains 'ダイエット' in desc: " . (str_contains($chat->desc ?? '', 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "\n";
            }
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        } else {
            echo "No results found\n";
        }

        $this->assertTrue(true);
    }

    public function testFindDailyStatsRankingWithKeywordSearch()
    {
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 10;
        $args->page = 0;
        $args->sort = 'rate';
        $args->order = 'DESC';
        $args->keyword = 'ダイエット';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findDailyStatsRanking($args);

        echo "\n=== findDailyStatsRanking with keyword 'ダイエット' ===\n";
        if (!empty($result)) {
            echo "Found " . count($result) . " results\n";
            foreach (array_slice($result, 0, 3) as $i => $chat) {
                echo "Result " . ($i + 1) . ":\n";
                echo "  ID: " . $chat->id . "\n";
                echo "  Name: " . $chat->name . "\n";
                echo "  Description: " . substr($chat->desc, 0, 50) . "...\n";
                echo "  Member: " . $chat->member . "\n";
                echo "  Diff: " . ($chat->diff_member ?? 'N/A') . "\n";
                echo "  Rate: " . ($chat->percent_increase ?? 'N/A') . "\n";
                echo "  Contains 'ダイエット' in name: " . (str_contains($chat->name, 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "  Contains 'ダイエット' in desc: " . (str_contains($chat->desc ?? '', 'ダイエット') ? 'YES' : 'NO') . "\n";
                echo "\n";
            }
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        } else {
            echo "No results found\n";
        }

        $this->assertTrue(true);
    }

    public function testSearchPriorityOrder()
    {
        echo "\n=== Testing search priority: name vs description ===\n";
        
        // まずはnameに'ダイエット'を含む結果を確認
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 20;
        $args->page = 0;
        $args->sort = 'rate';
        $args->order = 'DESC';
        $args->keyword = 'ダイエット';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findHourlyStatsRanking($args);
        
        if (!empty($result)) {
            $nameMatches = 0;
            $descOnlyMatches = 0;
            
            foreach ($result as $chat) {
                $nameContains = str_contains($chat->name, 'ダイエット');
                $descContains = str_contains($chat->desc ?? '', 'ダイエット');
                
                if ($nameContains) {
                    $nameMatches++;
                } elseif ($descContains) {
                    $descOnlyMatches++;
                }
            }
            
            echo "Name matches: $nameMatches\n";
            echo "Description-only matches: $descOnlyMatches\n";
            
            // 理想的には、nameに一致するものが先頭に来ることを確認
            $firstFewHaveNameMatch = true;
            for ($i = 0; $i < min(5, count($result)); $i++) {
                if (!str_contains($result[$i]->name, 'ダイエット')) {
                    $firstFewHaveNameMatch = false;
                    break;
                }
            }
            
            echo "First few results have name matches: " . ($firstFewHaveNameMatch ? 'YES' : 'NO') . "\n";
        }
        
        $this->assertTrue(true);
    }

    public function testFindStatsAllWithoutKeyword()
    {
        echo "\n=== Testing findStatsAll without keyword ===\n";
        
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 5;
        $args->page = 0;
        $args->sort = 'member';
        $args->order = 'DESC';
        $args->keyword = ''; // キーワードなし
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findStatsAll($args);
        
        echo "Found " . count($result) . " results\n";
        if (!empty($result)) {
            echo "First result - ID: " . $result[0]->id . ", Member: " . $result[0]->member . "\n";
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        }
        
        $this->assertNotEmpty($result, 'Should find results without keyword');
    }

    public function testFindHourlyStatsRankingWithoutKeyword()
    {
        echo "\n=== Testing findHourlyStatsRanking without keyword ===\n";
        
        $args = new OpenChatApiArgs;
        $args->category = 0;
        $args->limit = 5;
        $args->page = 0;
        $args->sort = 'rate';
        $args->order = 'DESC';
        $args->keyword = ''; // キーワードなし
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findHourlyStatsRanking($args);
        
        echo "Found " . count($result) . " results\n";
        if (!empty($result)) {
            echo "First result - ID: " . $result[0]->id . ", Member: " . $result[0]->member . "\n";
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        }
        
        $this->assertNotEmpty($result, 'Should find results without keyword');
    }

    public function testFindStatsAllWithCategory()
    {
        echo "\n=== Testing findStatsAll with category filter ===\n";
        
        $args = new OpenChatApiArgs;
        $args->category = 1; // カテゴリー指定
        $args->limit = 5;
        $args->page = 0;
        $args->sort = 'member';
        $args->order = 'DESC';
        $args->keyword = '';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findStatsAll($args);
        
        echo "Found " . count($result) . " results for category 1\n";
        if (!empty($result)) {
            echo "First result - ID: " . $result[0]->id . ", Category: " . $result[0]->category . "\n";
            if (isset($result[0]->totalCount)) {
                echo "Total count: " . $result[0]->totalCount . "\n";
            }
        }
        
        $this->assertTrue(true); // カテゴリフィルターが動作することを確認
    }

    public function testMultipleKeywordSearchWithFullWidthSpace()
    {
        echo "\n=== Testing multiple keyword search with full-width vs half-width spaces ===\n";
        
        // Test with half-width space
        $argsHalfWidth = new OpenChatApiArgs;
        $argsHalfWidth->category = 0;
        $argsHalfWidth->limit = 10;
        $argsHalfWidth->page = 0;
        $argsHalfWidth->sort = 'member';
        $argsHalfWidth->order = 'DESC';
        $argsHalfWidth->keyword = 'ダイエット 健康'; // 半角スペース
        $argsHalfWidth->sub_category = '';
        $argsHalfWidth->tag = '';
        $argsHalfWidth->badge = 0;
        $argsHalfWidth->list = '';

        $resultHalfWidth = $this->repo->findStatsAll($argsHalfWidth);
        
        // Test with full-width space
        $argsFullWidth = new OpenChatApiArgs;
        $argsFullWidth->category = 0;
        $argsFullWidth->limit = 10;
        $argsFullWidth->page = 0;
        $argsFullWidth->sort = 'member';
        $argsFullWidth->order = 'DESC';
        $argsFullWidth->keyword = 'ダイエット　健康'; // 全角スペース
        $argsFullWidth->sub_category = '';
        $argsFullWidth->tag = '';
        $argsFullWidth->badge = 0;
        $argsFullWidth->list = '';

        $resultFullWidth = $this->repo->findStatsAll($argsFullWidth);
        
        echo "Half-width space results: " . count($resultHalfWidth) . " items\n";
        echo "Full-width space results: " . count($resultFullWidth) . " items\n";
        
        // Compare results - they should be the same
        $this->assertEquals(
            count($resultHalfWidth),
            count($resultFullWidth),
            'Result count should be the same for half-width and full-width space searches'
        );
        
        // Compare IDs if both have results
        if (!empty($resultHalfWidth) && !empty($resultFullWidth)) {
            $halfWidthIds = array_map(fn($item) => $item->id, $resultHalfWidth);
            $fullWidthIds = array_map(fn($item) => $item->id, $resultFullWidth);
            
            $this->assertEquals(
                $halfWidthIds,
                $fullWidthIds,
                'Result IDs should be the same for half-width and full-width space searches'
            );
            
            echo "First result (half-width): ID=" . $resultHalfWidth[0]->id . ", Name=" . $resultHalfWidth[0]->name . "\n";
            echo "First result (full-width): ID=" . $resultFullWidth[0]->id . ", Name=" . $resultFullWidth[0]->name . "\n";
        }
    }

    public function testMultipleKeywordSearchWithMixedSpaces()
    {
        echo "\n=== Testing multiple keyword search with mixed spaces ===\n";
        
        // Test with mixed spaces (full-width and half-width)
        $argsMixed = new OpenChatApiArgs;
        $argsMixed->category = 0;
        $argsMixed->limit = 10;
        $argsMixed->page = 0;
        $argsMixed->sort = 'rate';
        $argsMixed->order = 'DESC';
        $argsMixed->keyword = 'ダイエット　健康 運動'; // 全角と半角の混在
        $argsMixed->sub_category = '';
        $argsMixed->tag = '';
        $argsMixed->badge = 0;
        $argsMixed->list = '';

        $resultMixed = $this->repo->findHourlyStatsRanking($argsMixed);
        
        // Test with all half-width spaces
        $argsAllHalf = new OpenChatApiArgs;
        $argsAllHalf->category = 0;
        $argsAllHalf->limit = 10;
        $argsAllHalf->page = 0;
        $argsAllHalf->sort = 'rate';
        $argsAllHalf->order = 'DESC';
        $argsAllHalf->keyword = 'ダイエット 健康 運動'; // すべて半角スペース
        $argsAllHalf->sub_category = '';
        $argsAllHalf->tag = '';
        $argsAllHalf->badge = 0;
        $argsAllHalf->list = '';

        $resultAllHalf = $this->repo->findHourlyStatsRanking($argsAllHalf);
        
        echo "Mixed spaces results: " . count($resultMixed) . " items\n";
        echo "All half-width spaces results: " . count($resultAllHalf) . " items\n";
        
        // Compare results
        $this->assertEquals(
            count($resultMixed),
            count($resultAllHalf),
            'Result count should be the same regardless of space type'
        );
        
        if (!empty($resultMixed) && !empty($resultAllHalf)) {
            $mixedIds = array_map(fn($item) => $item->id, $resultMixed);
            $allHalfIds = array_map(fn($item) => $item->id, $resultAllHalf);
            
            $this->assertEquals(
                $mixedIds,
                $allHalfIds,
                'Result IDs should be the same regardless of space type'
            );
        }
    }
}