<?php

declare(strict_types=1);

namespace App\Services\OgImage;

/**
 * NotoColorEmoji.ttf に埋め込まれたカラー絵文字ビットマップ(CBDT/CBLC・PNG形式)を取り出すリーダー。
 *
 * GD の imagettftext はビットマップ(カラー)絵文字フォントを描けない（"Could not set character size"）ため、
 * フォントから該当グリフのカラーPNG(109ppem, 136x128前後)を直接取り出し、GD 側で合成する。
 * 依存は無し（純PHP＋同梱フォント）＝ local/本番で同一。cmap は (3,10) format 12、
 * CBLC は indexFormat 1 / imageFormat 17（NotoColorEmoji の実構造）にのみ対応する。
 *
 * 解析はプロセス内で一度だけ行い（cp→gid と gid→[CBDT位置,長さ] を構築）、以降は getPng() がO(1)。
 * フォント欠落・想定外構造なら「絵文字を出さない」に穏当に縮退する（描画自体は止めない）。
 */
class NotoColorEmojiReader
{
    private bool $loaded = false;
    private bool $available = false;
    private string $data = '';
    /** @var array<int,int> codepoint => glyphId */
    private array $cmap = [];
    /** @var array<int,array{0:int,1:int}> glyphId => [CBDT内の絶対位置, 長さ] */
    private array $index = [];

    public function __construct(
        private string $fontPath,
    ) {}

    /** 指定コードポイントのカラー絵文字PNGバイト列を返す。無ければ null。 */
    public function getPng(int $codepoint): ?string
    {
        $this->load();
        if (!$this->available) {
            return null;
        }
        $gid = $this->cmap[$codepoint] ?? null;
        if ($gid === null || !isset($this->index[$gid])) {
            return null;
        }
        [$pos, $len] = $this->index[$gid];
        if ($len <= 9) {
            return null;
        }
        // imageFormat 17: smallGlyphMetrics(5) + dataLen(uint32) + PNG
        $pngLen = $this->u32($pos + 5);
        $png = substr($this->data, $pos + 9, $pngLen);
        return (strlen($png) >= 8 && str_starts_with($png, "\x89PNG")) ? $png : null;
    }

    /** この絵文字フォントが使えるか（存在＆解析成功） */
    public function isAvailable(): bool
    {
        $this->load();
        return $this->available;
    }

    private function u16(int $o): int
    {
        return (ord($this->data[$o]) << 8) | ord($this->data[$o + 1]);
    }

    private function u32(int $o): int
    {
        return (ord($this->data[$o]) << 24) | (ord($this->data[$o + 1]) << 16)
            | (ord($this->data[$o + 2]) << 8) | ord($this->data[$o + 3]);
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;

        if (!is_file($this->fontPath)) {
            return;
        }
        $data = file_get_contents($this->fontPath);
        if ($data === false || strlen($data) < 12) {
            return;
        }
        $this->data = $data;

        // テーブルディレクトリ
        $numTables = $this->u16(4);
        $tabs = [];
        for ($i = 0; $i < $numTables; $i++) {
            $o = 12 + $i * 16;
            if ($o + 16 > strlen($data)) {
                return;
            }
            $tabs[substr($data, $o, 4)] = [$this->u32($o + 8), $this->u32($o + 12)];
        }
        if (!isset($tabs['cmap'], $tabs['CBLC'], $tabs['CBDT'])) {
            return;
        }

        if (!$this->buildCmap($tabs['cmap'][0])) {
            return;
        }
        if (!$this->buildIndex($tabs['CBLC'][0], $tabs['CBDT'][0])) {
            return;
        }
        $this->available = true;
    }

    /** cmap の (3,10) format 12 サブテーブルから cp→gid を構築 */
    private function buildCmap(int $cmapOff): bool
    {
        $nsub = $this->u16($cmapOff + 2);
        $best = null;
        for ($i = 0; $i < $nsub; $i++) {
            $r = $cmapOff + 4 + $i * 8;
            $pid = $this->u16($r);
            $eid = $this->u16($r + 2);
            $off = $this->u32($r + 4);
            if ($pid === 3 && $eid === 10) {
                $best = $cmapOff + $off;
            }
        }
        if ($best === null || $this->u16($best) !== 12) {
            return false;
        }
        $nGroups = $this->u32($best + 12);
        $nGroups = min($nGroups, 100000);
        for ($g = 0; $g < $nGroups; $g++) {
            $go = $best + 16 + $g * 12;
            $sc = $this->u32($go);
            $ec = $this->u32($go + 4);
            $sg = $this->u32($go + 8);
            if ($ec - $sc > 5000) {
                continue; // 異常に広いグループは無視（安全）
            }
            for ($cp = $sc; $cp <= $ec; $cp++) {
                $this->cmap[$cp] = $sg + ($cp - $sc);
            }
        }
        return $this->cmap !== [];
    }

    /** CBLC の最初のstrikeから gid→[CBDT位置,長さ] を構築（indexFormat1 / imageFormat17 のみ） */
    private function buildIndex(int $cblc, int $cbdt): bool
    {
        $numSizes = $this->u32($cblc + 4);
        if ($numSizes < 1) {
            return false;
        }
        $bst = $cblc + 8; // 最初の bitmapSizeTable
        $isaOff = $this->u32($bst);
        $numIST = $this->u32($bst + 8);
        $isaBase = $cblc + $isaOff;

        for ($i = 0; $i < $numIST; $i++) {
            $e = $isaBase + $i * 8;
            $first = $this->u16($e);
            $last = $this->u16($e + 2);
            $add = $this->u32($e + 4);
            $ist = $isaBase + $add;
            $ifmt = $this->u16($ist);
            $imgfmt = $this->u16($ist + 2);
            $ido = $this->u32($ist + 4);
            if ($ifmt !== 1 || $imgfmt !== 17) {
                continue; // 対応形式のみ
            }
            $arr = $ist + 8;
            for ($gid = $first; $gid <= $last; $gid++) {
                $k = $gid - $first;
                $o0 = $this->u32($arr + $k * 4);
                $o1 = $this->u32($arr + ($k + 1) * 4);
                if ($o1 > $o0) {
                    $this->index[$gid] = [$cbdt + $ido + $o0, $o1 - $o0];
                }
            }
        }
        return $this->index !== [];
    }
}
