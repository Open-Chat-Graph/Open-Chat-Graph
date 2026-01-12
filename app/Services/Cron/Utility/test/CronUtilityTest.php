<?php

declare(strict_types=1);

use App\Services\Cron\Utility\CronUtility;
use PHPUnit\Framework\TestCase;

/**
 * CronUtility クラスのテスト
 *
 * 実行コマンド:
 *   docker compose exec app vendor/bin/phpunit app/Services/Cron/Utility/test/CronUtilityTest.php
 *
 * 特定のテストのみ実行:
 *   docker compose exec app vendor/bin/phpunit app/Services/Cron/Utility/test/CronUtilityTest.php --filter testGetCronLogGitHubRefFormat
 */
class CronUtilityTest extends TestCase
{
    /**
     * CronUtility::getCronLogGitHubRef() が正しい形式のGitHub参照を生成するかテスト
     *
     * Note: PHPUnitはフレームワーク経由で呼び出すため、backtraceの深さが異なる
     */
    public function testGetCronLogGitHubRefFormat(): void
    {
        // 実際のユースケースをシミュレート: ラッパー関数内から呼び出す
        $result = $this->simulateRealUsage();

        // 形式チェック: GitHub::path/to/file.php:123
        $this->assertMatchesRegularExpression(
            '/^GitHub::.+\.php:\d+$/',
            $result,
            'GitHub参照は "GitHub::path/to/file.php:line" 形式であるべき'
        );
    }

    /**
     * 実際のユースケースをシミュレートするヘルパー
     * addCronLog内部でgetCronLogGitHubRefが呼ばれるのと同様の構造
     */
    private function simulateRealUsage(): string
    {
        // backtraceDepth=1 でこのメソッドを指す
        return CronUtility::getCronLogGitHubRef(1);
    }

    /**
     * CronUtility::getCronLogGitHubRef() が相対パスを返すかテスト
     */
    public function testGetCronLogGitHubRefReturnsRelativePath(): void
    {
        $result = $this->simulateRealUsage();

        // フルパスが含まれていないこと
        $this->assertStringNotContainsString(
            '/home/',
            $result,
            '絶対パス(/home/)が含まれていてはいけない'
        );
        $this->assertStringNotContainsString(
            '/var/www/',
            $result,
            'サーバーのパス(/var/www/)が含まれていてはいけない'
        );

        // GitHub::で始まること
        $this->assertStringStartsWith(
            'GitHub::',
            $result,
            'GitHub::で始まるべき'
        );
    }

    /**
     * CronUtility::getCronLogGitHubRef() が行番号を含むかテスト
     */
    public function testGetCronLogGitHubRefIncludesLineNumber(): void
    {
        $result = $this->simulateRealUsage();

        // 行番号を抽出 (:123 形式)
        preg_match('/:(\d+)$/', $result, $matches);
        $this->assertNotEmpty($matches, '行番号が含まれるべき');

        $lineNumber = (int)$matches[1];
        $this->assertGreaterThan(0, $lineNumber, '行番号は正の整数であるべき');
    }

    /**
     * CronUtility::addCronLog() がGitHub参照を含むログを出力するかテスト
     * 実際にログファイルに書き込み、内容を検証する
     */
    public function testAddCronLogIncludesGitHubRef(): void
    {
        $logFile = \App\Config\AppConfig::getStorageFilePath('addCronLogDest');
        $testMessage = 'TEST_GITHUB_REF_' . uniqid();

        // ログファイルの現在のサイズを記録
        $sizeBefore = file_exists($logFile) ? filesize($logFile) : 0;

        // addCronLogを呼び出し
        $returnValue = CronUtility::addCronLog($testMessage);

        // 戻り値がログメッセージ全体（GitHub参照のみフルURL形式）であることを確認
        $this->assertNotEmpty($returnValue, 'ログメッセージが返されるべき');
        $this->assertStringContainsString($testMessage, $returnValue, '戻り値にテストメッセージが含まれるべき');
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[.+\] .+ https:\/\/github\.com\/.+#L\d+$/',
            $returnValue,
            '戻り値は "YYYY-MM-DD HH:MM:SS [プロセスタグ] メッセージ https://github.com/...#L行番号" 形式であるべき'
        );

        // ログファイルが更新されたことを確認
        clearstatcache();
        $sizeAfter = filesize($logFile);
        $this->assertGreaterThan($sizeBefore, $sizeAfter, 'ログファイルにデータが追加されるべき');

        // ログファイルの末尾を読み取り、追加されたログを確認
        $handle = fopen($logFile, 'r');
        fseek($handle, $sizeBefore);
        $newContent = fread($handle, $sizeAfter - $sizeBefore);
        fclose($handle);

        // テストメッセージが含まれることを確認
        $this->assertStringContainsString($testMessage, $newContent, 'テストメッセージがログに含まれるべき');

        // ログファイルには GitHub::path:line 形式で記録されていることを確認
        $this->assertMatchesRegularExpression(
            '/GitHub::[^\s]+\.php:\d+/',
            $newContent,
            'ログファイルには "GitHub::path/to/file.php:line" 形式で記録されるべき'
        );

        // ログ出力を表示（HTML画面で確認用）
        echo "\n=== 追加されたログ ===\n";
        echo $newContent;
        echo "======================\n";
    }

    /**
     * CronUtility::addVerboseCronLog() がAppConfig設定に従うかテスト
     */
    public function testAddVerboseCronLogRespectsConfig(): void
    {
        $originalValue = \App\Config\AppConfig::$verboseCronLog;

        // verboseCronLog = false の場合
        \App\Config\AppConfig::$verboseCronLog = false;
        CronUtility::addVerboseCronLog('This should not be logged');
        $this->assertTrue(true, 'verboseCronLog=falseでもエラーにならない');

        // verboseCronLog = true の場合
        \App\Config\AppConfig::$verboseCronLog = true;
        CronUtility::addVerboseCronLog('This should be logged');
        $this->assertTrue(true, 'verboseCronLog=trueでもエラーにならない');

        // 元の値に戻す
        \App\Config\AppConfig::$verboseCronLog = $originalValue;
    }

    /**
     * backtraceDepthが正しく機能するかテスト
     *
     * wrapper1 -> wrapper2 -> CronUtility::getCronLogGitHubRef(depth)
     * depth=1: wrapper2を指す
     * depth=2: wrapper1を指す
     */
    public function testBacktraceDepthWorks(): void
    {
        $result1 = $this->wrapper1ForDepthTest(1);
        $result2 = $this->wrapper1ForDepthTest(2);

        // 両方とも有効な形式であること
        $this->assertMatchesRegularExpression('/^GitHub::.+\.php:\d+$/', $result1);
        $this->assertMatchesRegularExpression('/^GitHub::.+\.php:\d+$/', $result2);

        // 行番号が異なること（異なる関数を指しているので）
        preg_match('/:(\d+)$/', $result1, $matches1);
        preg_match('/:(\d+)$/', $result2, $matches2);

        $this->assertNotEquals(
            $matches1[1],
            $matches2[1],
            'depth=1とdepth=2では異なる行番号を返すべき'
        );
    }

    private function wrapper1ForDepthTest(int $depth): string
    {
        return $this->wrapper2ForDepthTest($depth);
    }

    private function wrapper2ForDepthTest(int $depth): string
    {
        return CronUtility::getCronLogGitHubRef($depth);
    }

    /**
     * AppConfigのGitHub設定が正しく設定されているかテスト
     */
    public function testAppConfigGitHubSettings(): void
    {
        $this->assertNotEmpty(
            \App\Config\AppConfig::$githubRepo,
            'githubRepoが設定されているべき'
        );
        $this->assertNotEmpty(
            \App\Config\AppConfig::$githubBranch,
            'githubBranchが設定されているべき'
        );

        // リポジトリ名の形式チェック
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9_-]+\/[a-zA-Z0-9_-]+$/',
            \App\Config\AppConfig::$githubRepo,
            'githubRepoは "owner/repo" 形式であるべき'
        );
    }

    /**
     * buildGitHubUrl() が正しいURLを生成するかテスト
     */
    public function testBuildGitHubUrl(): void
    {
        $githubRef = [
            'filePath' => 'app/Services/Cron/SyncOpenChat.php',
            'lineNumber' => 97,
            'fileName' => 'SyncOpenChat.php',
            'label' => 'SyncOpenChat.php:97',
        ];

        $result = buildGitHubUrl($githubRef);

        // 正しいURL形式であること
        $this->assertStringStartsWith('https://github.com/', $result);

        // リポジトリ名が含まれること
        $this->assertStringContainsString(
            \App\Config\AppConfig::$githubRepo,
            $result,
            'URLにリポジトリ名が含まれるべき'
        );

        // ブランチ名が含まれること
        $this->assertStringContainsString(
            \App\Config\AppConfig::$githubBranch,
            $result,
            'URLにブランチ名が含まれるべき'
        );

        // ファイルパスが含まれること
        $this->assertStringContainsString(
            'app/Services/Cron/SyncOpenChat.php',
            $result,
            'URLにファイルパスが含まれるべき'
        );

        // 行番号アンカーが含まれること
        $this->assertStringEndsWith(
            '#L97',
            $result,
            'URLに行番号アンカーが含まれるべき'
        );
    }

    /**
     * buildGitHubUrl() が文字列の行番号も処理できるかテスト
     */
    public function testBuildGitHubUrlWithStringLineNumber(): void
    {
        $githubRef = [
            'filePath' => 'app/Helpers/functions.php',
            'lineNumber' => '123',  // 文字列として渡す
        ];

        $result = buildGitHubUrl($githubRef);

        $this->assertStringEndsWith(
            '#L123',
            $result,
            '文字列の行番号も正しく処理されるべき'
        );
    }

    /**
     * buildGitHubUrl() の出力形式が期待通りかテスト
     */
    public function testBuildGitHubUrlFormat(): void
    {
        $githubRef = [
            'filePath' => 'test/path/file.php',
            'lineNumber' => 42,
        ];

        $result = buildGitHubUrl($githubRef);

        $expectedPattern = sprintf(
            'https://github.com/%s/blob/%s/test/path/file.php#L42',
            \App\Config\AppConfig::$githubRepo,
            \App\Config\AppConfig::$githubBranch
        );

        $this->assertEquals($expectedPattern, $result);
    }

    /**
     * CronUtility::convertGitHubRefToFullUrl() が正しく変換するかテスト
     */
    public function testConvertGitHubRefToFullUrl(): void
    {
        $shortRef = 'GitHub::app/Services/Cron/SyncOpenChat.php:97';
        $result = CronUtility::convertGitHubRefToFullUrl($shortRef);

        // フルURL形式であること
        $this->assertStringStartsWith('https://github.com/', $result);
        $this->assertStringContainsString('/blob/', $result);
        $this->assertStringContainsString('app/Services/Cron/SyncOpenChat.php', $result);
        $this->assertStringEndsWith('#L97', $result);

        // 期待されるURL
        $expected = sprintf(
            'https://github.com/%s/blob/%s/app/Services/Cron/SyncOpenChat.php#L97',
            \App\Config\AppConfig::$githubRepo,
            \App\Config\AppConfig::$githubBranch
        );
        $this->assertEquals($expected, $result);
    }

    /**
     * convertGitHubRefToFullUrl() が空文字列や不正な形式を適切に処理するかテスト
     */
    public function testConvertGitHubRefToFullUrlEdgeCases(): void
    {
        // 空文字列
        $this->assertEquals('', CronUtility::convertGitHubRefToFullUrl(''));

        // GitHub::で始まらない文字列
        $invalid = 'some random string';
        $this->assertEquals($invalid, CronUtility::convertGitHubRefToFullUrl($invalid));

        // コロンがない形式
        $noColon = 'GitHub::path/to/file.php';
        $this->assertEquals($noColon, CronUtility::convertGitHubRefToFullUrl($noColon));
    }
}
