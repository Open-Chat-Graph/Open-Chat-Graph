<?php

declare(strict_types=1);

namespace App\Services\Storage;

/**
 * ファイル書き込み操作のインターフェース
 *
 * アプリケーション全体でファイル書き込みを統一的に扱い、
 * テスト時のタイムスタンプ偽装などに対応できるようにする
 */
interface FileStorageInterface
{
    /**
     * ストレージファイルのパスを取得
     *
     * @param string $storageFileName ストレージファイルキー
     * @return string ファイルの絶対パス
     */
    public function getStorageFilePath(string $storageFileName): string;

    /**
     * ファイルに安全に書き込む（アトミックな書き込み）
     *
     * 一時ファイルに書き込んでからrenameすることで、
     * 書き込み中のファイルが読み込まれることを防ぐ
     *
     * @param string $filepath 書き込み先のファイルパス（@で始まる場合はストレージキー名として解決）
     * @param string $content 書き込む内容
     * @return void
     * @throws \RuntimeException 書き込みに失敗した場合
     */
    public function safeFileRewrite(string $filepath, string $content): void;

    /**
     * シリアライズしたデータをファイルに保存
     *
     * @param string $filepath 保存先のファイルパス（@で始まる場合はストレージキー名として解決）
     * @param mixed $data シリアライズして保存するデータ
     * @return void
     * @throws \RuntimeException 保存に失敗した場合
     */
    public function saveSerializedFile(string $filepath, mixed $data): void;

    /**
     * ファイルに内容を書き込む（file_put_contentsのラッパー）
     *
     * @param string $filepath 書き込み先のファイルパス（@で始まる場合はストレージキー名として解決）
     * @param string $content 書き込む内容
     * @return void
     * @throws \RuntimeException 書き込みに失敗した場合
     */
    public function putContents(string $filepath, string $content): void;

    /**
     * ファイルの内容を読み込む（file_get_contentsのラッパー）
     *
     * @param string $filepath 読み込むファイルパス（@で始まる場合はストレージキー名として解決）
     * @return string ファイルの内容
     * @throws \RuntimeException 読み込みに失敗した場合
     */
    public function getContents(string $filepath): string;

    /**
     * シリアライズされたデータをファイルから読み込む
     *
     * @param string $filepath 読み込むファイルパス（@で始まる場合はストレージキー名として解決）
     * @return mixed デシリアライズされたデータ、ファイルが存在しないか読み込みに失敗した場合はfalse
     */
    public function getSerializedFile(string $filepath): mixed;
}
