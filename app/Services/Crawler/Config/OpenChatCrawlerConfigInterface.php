<?php

namespace App\Services\Crawler\Config;

interface OpenChatCrawlerConfigInterface
{
    /**
     * LINE内部URL（招待ページ）
     */
    public function getLineInternalUrl(): string;

    /**
     * クローリング用ユーザーエージェント
     */
    public function getUserAgent(): string;

    /**
     * LINE URL マッチパターン
     * @return array<string, string>
     */
    public function getLineUrlMatchPattern(): array;

    /**
     * LINE画像URL
     */
    public function getLineImgUrl(): string;

    /**
     * LINE画像プレビューパス
     */
    public function getLineImgPreviewPath(): string;

    /**
     * 画像MIMEタイプマッピング
     * @return array<string, string>
     */
    public function getImgMimeType(): array;

    /**
     * LINE内部URLマッチパターン
     */
    public function getLineInternalUrlMatchPattern(): string;

    /**
     * DOMクラス名: 名前
     */
    public function getDomClassName(): string;

    /**
     * DOMクラス名: メンバー数
     */
    public function getDomClassMember(): string;

    /**
     * DOMクラス名: 説明
     */
    public function getDomClassDescription(): string;

    /**
     * DOMクラス名: 画像
     */
    public function getDomClassImg(): string;

    /**
     * 保存画像品質
     */
    public function getStoreImgQuality(): int;

    /**
     * OpenChat API OCデータ取得URL生成（emidから）
     */
    public function generateOpenChatApiOcDataFromEmidUrl(string $emid): string;

    /**
     * OpenChat API ランキングデータURL生成
     */
    public function generateOpenChatApiRankingDataUrl(string $category, string $ct): string;

    /**
     * OpenChat API 急上昇データURL生成
     */
    public function generateOpenChatApiRisingDataUrl(string $category, string $ct): string;

    /**
     * OpenChat API OCデータ取得用ヘッダー（emidから）
     * @return array<string, array<int, string>>
     */
    public function getOpenChatApiOcDataFromEmidDownloaderHeader(): array;
}
