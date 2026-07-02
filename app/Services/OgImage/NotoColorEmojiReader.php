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
    /** @var array<string,int> "先頭gid:2文字目gid" => 合字glyphId（国旗など2成分の合字） */
    private array $liga = [];

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
        return $gid === null ? null : $this->pngForGid($gid);
    }

    /**
     * 国旗など「地域表示文字2つ」の合字カラーPNGを返す。合字が無ければ null。
     * 国旗絵文字は単一コードポイントではなく、地域表示文字2つ(例 🇯＋🇵)を GSUB の合字で
     * 1つの旗グリフにするため、cmap だけでは出せず GSUB を引く必要がある。
     */
    public function getFlagPng(int $cp1, int $cp2): ?string
    {
        $this->load();
        if (!$this->available) {
            return null;
        }
        $g1 = $this->cmap[$cp1] ?? null;
        $g2 = $this->cmap[$cp2] ?? null;
        if ($g1 === null || $g2 === null) {
            return null;
        }
        $lig = $this->liga[$g1 . ':' . $g2] ?? null;
        return $lig === null ? null : $this->pngForGid($lig);
    }

    /** glyphId に対応する CBDT 埋め込み PNG（imageFormat 17）を返す。無ければ null。 */
    private function pngForGid(int $gid): ?string
    {
        if (!isset($this->index[$gid])) {
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
        // 国旗など2成分の合字（任意・GSUB が無い/読めなくても絵文字描画自体は続行）
        if (isset($tabs['GSUB'])) {
            $this->buildLigatures($tabs['GSUB'][0]);
        }
        $this->available = true;
    }

    /**
     * GSUB の LigatureSubst(LookupType 4、Extension=7 経由も)から「2成分の合字」だけを
     * $this->liga（"先頭gid:2文字目gid" => 合字glyphId）に積む。国旗（地域表示文字2つ）が対象。
     * 想定外構造は各所の境界チェックで黙って読み飛ばす（描画は止めない）。
     */
    private function buildLigatures(int $gsubOff): void
    {
        $len = strlen($this->data);
        if ($gsubOff + 10 > $len) {
            return;
        }
        // GSUB header(1.0): major(2) minor(2) scriptListOff(2) featureListOff(2) lookupListOff(2)
        $ll = $gsubOff + $this->u16($gsubOff + 8);
        if ($ll + 2 > $len) {
            return;
        }
        $lookupCount = $this->u16($ll);
        for ($i = 0; $i < $lookupCount; $i++) {
            $lo = $ll + 2 + $i * 2;
            if ($lo + 2 > $len) {
                break;
            }
            $lookupOff = $ll + $this->u16($lo);
            if ($lookupOff + 6 > $len) {
                continue;
            }
            $type = $this->u16($lookupOff);
            $subCount = $this->u16($lookupOff + 4);
            for ($s = 0; $s < $subCount; $s++) {
                $so = $lookupOff + 6 + $s * 2;
                if ($so + 2 > $len) {
                    break;
                }
                $subOff = $lookupOff + $this->u16($so);
                $realType = $type;
                $realOff = $subOff;
                if ($type === 7) { // Extension: format(2) extType(2) extOffset(4)
                    if ($subOff + 8 > $len) {
                        continue;
                    }
                    $realType = $this->u16($subOff + 2);
                    $realOff = $subOff + $this->u32($subOff + 4);
                }
                if ($realType === 4) {
                    $this->parseLigatureSubst($realOff);
                }
            }
        }
    }

    /** LigatureSubstFormat1 を解析し、2成分の合字を $this->liga に積む */
    private function parseLigatureSubst(int $off): void
    {
        $len = strlen($this->data);
        if ($off + 6 > $len || $this->u16($off) !== 1) {
            return;
        }
        $coverage = $this->parseCoverage($off + $this->u16($off + 2)); // index => 先頭gid
        $ligSetCount = $this->u16($off + 4);
        for ($i = 0; $i < $ligSetCount; $i++) {
            $lso = $off + 6 + $i * 2;
            if ($lso + 2 > $len || !isset($coverage[$i])) {
                continue;
            }
            $first = $coverage[$i];
            $ligSetOff = $off + $this->u16($lso);
            if ($ligSetOff + 2 > $len) {
                continue;
            }
            $ligCount = $this->u16($ligSetOff);
            for ($j = 0; $j < $ligCount; $j++) {
                $lo = $ligSetOff + 2 + $j * 2;
                if ($lo + 2 > $len) {
                    break;
                }
                $ligOff = $ligSetOff + $this->u16($lo);
                if ($ligOff + 6 > $len) {
                    continue;
                }
                $ligGlyph = $this->u16($ligOff);
                $compCount = $this->u16($ligOff + 2);
                if ($compCount !== 2) {
                    continue; // 旗は2成分（地域表示文字2つ）だけ扱う
                }
                $comp2 = $this->u16($ligOff + 4); // components[1]（先頭以外の1つ）
                $this->liga[$first . ':' . $comp2] = $ligGlyph;
            }
        }
    }

    /**
     * Coverage テーブル(format 1/2)を「coverage index => glyphId」に展開する。
     *
     * @return array<int,int>
     */
    private function parseCoverage(int $off): array
    {
        $len = strlen($this->data);
        if ($off + 4 > $len) {
            return [];
        }
        $fmt = $this->u16($off);
        $out = [];
        if ($fmt === 1) {
            $count = $this->u16($off + 2);
            for ($i = 0; $i < $count; $i++) {
                $o = $off + 4 + $i * 2;
                if ($o + 2 > $len) {
                    break;
                }
                $out[$i] = $this->u16($o);
            }
        } elseif ($fmt === 2) {
            $rangeCount = $this->u16($off + 2);
            for ($r = 0; $r < $rangeCount; $r++) {
                $o = $off + 4 + $r * 6;
                if ($o + 6 > $len) {
                    break;
                }
                $start = $this->u16($o);
                $end = $this->u16($o + 2);
                $startCovIdx = $this->u16($o + 4);
                if ($end < $start || $end - $start > 5000) {
                    continue;
                }
                for ($g = $start; $g <= $end; $g++) {
                    $out[$startCovIdx + ($g - $start)] = $g;
                }
            }
        }
        return $out;
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
