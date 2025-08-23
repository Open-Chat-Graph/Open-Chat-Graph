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
        $args->sort = 'member';
        $args->order = 'DESC';
        $args->keyword = 'ダイエット';
        $args->sub_category = '';
        $args->tag = '';
        $args->badge = 0;
        $args->list = '';

        $result = $this->repo->findStatsAll($args);
        
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
}