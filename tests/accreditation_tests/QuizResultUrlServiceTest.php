<?php

declare(strict_types=1);

use App\Services\Accreditation\QuizApi\Dto\ResultJsonDecoder;
use App\Services\Accreditation\QuizApi\QuizResultUrlService;
use PHPUnit\Framework\TestCase;

class QuizResultUrlServiceTest extends TestCase
{
    private ResultJsonDecoder $decoder;
    private QuizResultUrlService $inst;

    public function test()
    {
        $this->inst = app(QuizResultUrlService::class);

        $this->decoder = app(ResultJsonDecoder::class);

        $jsonString =
            '[
                    {
                        "type": "MCQs",
                        "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\uff08\u30aa\u30d7\u30c1\u30e3\uff09\u3067\u3001\u305f\u304f\u3055\u3093\u306e\u4eba\u3068\u8a71\u3059\u3068\u304d\u306b\u4f7f\u3046\u300c\u90e8\u5c4b\u300d\u306e\u3053\u3068\u3092\u4f55\u3068\u8a00\u3046\u3067\u3057\u3087\u3046\u304b\uff1f",
                        "choices": [
                            "\u304a\u5316\u3051\u306e\u90e8\u5c4b",
                            "\u30c8\u30fc\u30af\u30eb\u30fc\u30e0",
                            "\u304a\u83d3\u5b50\u306e\u90e8\u5c4b",
                            "\u5fb9\u5b50\u306e\u90e8\u5c4b"
                        ],
                        "correctAnswers": [
                            "\u30c8\u30fc\u30af\u30eb\u30fc\u30e0"
                        ],
                        "score": 1,
                        "contributor": {
                            "name": "\u7530\u4e2d\u30a8\u30eb",
                            "roomName": "\u5c0f\u8aac\u5bb6\u306b\u306a\u308d\u3046\u30fb\u30ab\u30af\u30e8\u30e0\u30fb\u30a2\u30eb\u30d5\u30a1\u30dd\u30ea\u30b9\u30fb\u30ce\u30d9\u30eb\u30a2\u30c3\u30d7\uff0b\u30fbNola\u30ce\u30d9\u30eb\u30fbPixiv\u306a\u3069\u306e\u60c5\u5831\u4ea4\u63db\u6240",
                            "url": "https:\/\/line.me\/ti\/g2\/3pJ0r_H54ATQxzbyAEN7aokOD5a3rG-MgOZ6Xg"
                        },
                        "explanation": "LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u307f\u3093\u306a\u3067\u8a71\u3057\u5408\u3046\u5834\u6240\u3092\u300c\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u300d\u3068\u547c\u3073\u307e\u3059\u3002\u3053\u3053\u3067\u3044\u308d\u3044\u308d\u306a\u4eba\u3068\u697d\u3057\u304f\u304a\u3057\u3083\u3079\u308a\u304c\u3067\u304d\u308b\u3093\u3067\u3059\u3002",
                        "source": {
                            "title": "\u30d8\u30eb\u30d7\u30bb\u30f3\u30bf\u30fc",
                            "url": "https:\/\/help.line.me\/line\/smartphone\/?contentId=20005375\u0026lang=ja#:~:text=%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E3%81%AE%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0,%E3%81%99%E3%82%8B%E3%81%93%E3%81%A8%E3%81%8C%E5%8F%AF%E8%83%BD%E3%81%A7%E3%81%99%E3%80%82"
                        },
                        "id": 95,
                        "selectedAnswer": "\u304a\u83d3\u5b50\u306e\u90e8\u5c4b",
                        "isMatch": false
                    },
                    {
                        "type": "MCQs",
                        "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                        "choices": [
                            "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                            "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                            "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                            "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                        ],
                        "correctAnswers": [
                            "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                        ],
                        "score": 1,
                        "contributor": {
                            "name": "\u30de\u30b9\u30af",
                            "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                            "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                        },
                        "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                        "source": {
                            "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                            "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                        },
                        "id": 13,
                        "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                        "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                            {
                                "type": "MCQs",
                                "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                                "choices": [
                                    "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                    "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                                    "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                                ],
                                "correctAnswers": [
                                    "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                                ],
                                "score": 1,
                                "contributor": {
                                    "name": "\u30de\u30b9\u30af",
                                    "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                                    "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                                },
                                "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                                "source": {
                                    "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                                    "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                                },
                                "id": 13,
                                "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                                "isMatch": true
                    },
                    {
                        "type": "MCQs",
                        "question": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u9055\u53cd\u304c\u3042\u3063\u305f\u5834\u5408\u3001LINE\u304b\u3089\u3069\u3093\u306a\u63aa\u7f6e\u304c\u53d6\u3089\u308c\u308b\u53ef\u80fd\u6027\u304c\u3042\u308b\uff1f",
                        "choices": [
                            "\u30c8\u30fc\u30af\u5185\u5bb9\u306e\u6d41\u51fa",
                            "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                            "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5168\u4f53\u306e\u9589\u9396",
                            "\u5171\u540c\u7ba1\u7406\u8005\u306e\u8ffd\u52a0"
                        ],
                        "correctAnswers": [
                            "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62"
                        ],
                        "score": 1,
                        "contributor": {
                            "name": "\u30de\u30b9\u30af",
                            "roomName": "\ud83d\udd30\u30aa\u30d7\u30c1\u30e3\u306e\u76f8\u8ac7\u5ba4\ud83d\udd30LINE\u516c\u8a8d\ud83c\udfc5\u8d85\u521d\u5fc3\u8005\u3082\u7ba1\u7406\u4eba\u3082\u526f\u5b98\u3082\u8cea\u554f\u30fb\u5ba3\u4f1d\u30fb\u96d1\u8ac7OK\u203c\ufe0e\u30b5\u30dd\u30fc\u30c8\u30eb\u30fc\u30e0",
                            "url": "https:\/\/line.me\/ti\/g2\/ju-Wb8w8IG2BxQhYCPU0v9-XUCD6B6YBzHmAJQ"
                        },
                        "explanation": "\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u3067\u306f\u3001\u5b89\u5168\u3067\u5b89\u5fc3\u306a\u30b3\u30df\u30e5\u30cb\u30b1\u30fc\u30b7\u30e7\u30f3\u74b0\u5883\u3092\u5b88\u308b\u305f\u3081\u306b\u3001\u30c8\u30fc\u30af\u3084\u6295\u7a3f\u5185\u5bb9\u3092\u5e38\u306b\u30e2\u30cb\u30bf\u30ea\u30f3\u30b0\u3057\u3066\u3044\u307e\u3059\u3002\u3082\u3057\u5229\u7528\u898f\u7d04\u3084\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3\u306b\u9055\u53cd\u3057\u305f\u5834\u5408\u3001LINE\u306f\u8a72\u5f53\u3059\u308b\u30c8\u30fc\u30af\u30eb\u30fc\u30e0\u3084\u6295\u7a3f\u3092\u524a\u9664\u3057\u3001\u9055\u53cd\u3057\u305f\u30e6\u30fc\u30b6\u30fc\u306e\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8\u5229\u7528\u3092\u505c\u6b62\u3059\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002\u3055\u3089\u306b\u3001\u91cd\u7be4\u306a\u5834\u5408\u306fLINE\u30a2\u30d7\u30ea\u306e\u5229\u7528\u505c\u6b62\u307e\u3067\u884c\u308f\u308c\u308b\u3053\u3068\u304c\u3042\u308a\u307e\u3059\u3002",
                        "source": {
                            "title": "\u5b89\u5fc3\u30fb\u5b89\u5168\u30ac\u30a4\u30c9\u30e9\u30a4\u30f3 | LINE\u30aa\u30fc\u30d7\u30f3\u30c1\u30e3\u30c3\u30c8",
                            "url": "https:\/\/openchat-jp.line.me\/other\/guideline#:~:text=%E5%88%A9%E7%94%A8%E8%A6%8F%E7%B4%84%E3%82%84%E3%82%AC%E3%82%A4%E3%83%89%E3%83%A9%E3%82%A4%E3%83%B3%E3%81%AB%E9%81%95%E5%8F%8D%E3%81%97%E3%81%9F%E5%A0%B4%E5%90%88%E3%81%AF%E3%80%81%E3%83%88%E3%83%BC%E3%82%AF%E3%83%AB%E3%83%BC%E3%83%A0%E3%83%BB%E6%8A%95%E7%A8%BF%E3%81%AE%E5%89%8A%E9%99%A4%E3%81%8A%E3%82%88%E3%81%B3%E9%81%95%E5%8F%8D%E3%83%A6%E3%83%BC%E3%82%B6%E3%81%AE%E3%82%AA%E3%83%BC%E3%83%97%E3%83%B3%E3%83%81%E3%83%A3%E3%83%83%E3%83%88%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AB%E5%8A%A0%E3%81%88%E3%80%81LINE%E3%82%A2%E3%83%97%E3%83%AA%E3%81%AE%E5%88%A9%E7%94%A8%E5%81%9C%E6%AD%A2%E3%81%AE%E6%8E%AA%E7%BD%AE%E3%82%92%E8%A1%8C%E3%81%86%E5%A0%B4%E5%90%88%E3%82%82%E3%81%94%E3%81%96%E3%81%84%E3%81%BE%E3%81%99%E3%81%AE%E3%81%A7%E3%81%94%E6%B3%A8%E6%84%8F%E3%81%8F%E3%81%A0%E3%81%95%E3%81%84%E3%80%82"
                        },
                        "id": 13,
                        "selectedAnswer": "\u9055\u53cd\u30a2\u30ab\u30a6\u30f3\u30c8\u306e\u5229\u7528\u505c\u6b62",
                        "isMatch": true
                    }
                ]';

        $topic = $this->decoder->decode($jsonString);

        $result = $this->inst->generate($topic, 2, 1, 30, 'b', 'ぴかっぴかちゅう');

        debug(base64_encode(gzdeflate($result)));

        $this->assertIsBool(true);
    }
}
