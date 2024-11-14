<?php

declare(strict_types=1);

namespace App\Services\Furigana;

class YahooFuriganaService
{
    private string $apiUrl = 'https://jlp.yahooapis.jp/FuriganaService/V2/furigana';

    private string $appId;
    private int $grade;

    public function splitArrayBySize(array $strings, $maxSize = 4096, $buffer = 8)
    {
        $stringsCountByte = count($strings) * 8;
        $result = [];
        $currentArray = [];
        $currentSize = 0;
        $threshold = $maxSize - $buffer; // 少し余裕を見た閾値

        foreach ($strings as $string) {
            $stringSize = strlen($string);

            // 現在の配列に追加したら閾値を超える場合、新しい配列を作成
            if (($currentSize + $stringSize) > ($threshold - $stringsCountByte)) {
                $result[] = $currentArray;
                $currentArray = [];
                $currentSize = 0;
            }

            $currentArray[] = $string;
            $currentSize += $stringSize;
        }

        // 最後の配列を結果に追加
        if (!empty($currentArray)) {
            $result[] = $currentArray;
        }

        return $result;
    }

    /**
     * @param string[] $sentences
     * @return string[]
     */
    public function getFuriganaFromArray(array $sentences, string $appId, int $grade = 2): array
    {
        $result = [];
        foreach ($this->splitArrayBySize($sentences) as $strings) {
            $string = implode("ÅÅ", $strings);
            $res = $this->getFurigana($string, $appId, $grade);
            $result[] = explode('ÅÅ', $res);
        }

        return array_reduce($result, 'array_merge', []);
    }

    public function getFurigana(string $sentence, string $appId, int $grade = 2): string
    {
        $this->appId = $appId;
        $this->grade = $grade;

        if (empty($sentence)) {
            var_dump($sentence);
            throw new \InvalidArgumentException('Sentence cannot be empty.');
        }

        $ch = curl_init();
        $payload = $this->createPayload($sentence);

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \RuntimeException('CURL Error: ' . curl_error($ch));
        }

        curl_close($ch);

        return $this->processResult($result);
    }

    private function createPayload($sentence)
    {
        return [
            'id' => '1234-1',
            'jsonrpc' => '2.0',
            'method' => 'jlp.furiganaservice.furigana',
            'params' => [
                'q' => $sentence,
                'grade' => $this->grade
            ]
        ];
    }

    private function getHeaders()
    {
        return [
            'Content-Type: application/json',
            'User-Agent: Yahoo AppID: ' . $this->appId
        ];
    }

    private function processResult($result)
    {
        $result = json_decode($result, true);

        if (isset($result['error'])) {
            throw new \RuntimeException('API Error: ' . $result['error']['message']);
        }

        $surface = '';
        foreach ($result['result']['word'] as $value) {
            $surface .= $this->processWord($value);
        }

        return $surface;
    }

    private function processWord($word)
    {
        $surface = '';
        if (isset($word['furigana'])) {
            if (isset($word['subword'])) {
                foreach ($word['subword'] as $subvalue) {
                    $surface .= $this->processSubword($subvalue);
                }
            } else {
                $surface .= $this->formatFurigana($word);
            }
        } else {
            $surface .= $word['surface'];
        }
        return $surface;
    }

    private function processSubword($subword)
    {
        return $subword['furigana'] === $subword['surface'] ? $subword['furigana'] : $this->formatFurigana($subword);
    }

    private function formatFurigana($word)
    {
        return "<ruby>{$word['surface']}<rt>{$word['furigana']}</rt></ruby>";
    }
}