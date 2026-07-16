<?php

declare(strict_types=1);

namespace App\Controllers\Pages;

use App\Services\PublicApi\PublicApiResponder;

final class PublicApiDocumentationController
{
    private const OPENAPI_PATH = __DIR__ . '/../../OpenApi/openapi.json';

    public function index()
    {
        header('X-Robots-Tag: noindex, follow');
        $_css = ['components/site_header', 'components/site_footer', 'pages/terms'];
        $canonical = url('api');
        $noindex = true;
        $_meta = meta()
            ->setTitle('公開データAPI')
            ->setDescription('オプチャグラフの部屋・ランキング・テーマ・統計を取得できる公開JSON APIです。')
            ->setOgpDescription('オプチャグラフの公開JSON API仕様と利用方法。')
            ->setCanonicalUrl($canonical);
        return view('api_documentation_content', compact('_css', '_meta', 'canonical', 'noindex'));
    }

    public function openapi(PublicApiResponder $responder): \Shadow\Kernel\Response
    {
        $document = json_decode((string)file_get_contents(self::OPENAPI_PATH), true, flags: JSON_THROW_ON_ERROR);
        return $responder->respond($document, (string)filemtime(self::OPENAPI_PATH), url('api'), 200);
    }

    public function llms()
    {
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Robots-Tag: noindex, follow');
        echo "# Open Chat Graph\n\n"
            . "LINE OpenChat room statistics, rankings, and theme discovery.\n\n"
            . "- API documentation: " . url('api') . "\n"
            . "- OpenAPI 3.1: " . url('api/openapi.json') . "\n"
            . "- Methodology: " . url('policy') . "#methodology\n"
            . "- Rankings: " . url('ranking') . "\n"
            . "- Overall statistics: " . url('labs/all-room-stats') . "\n";
        exit;
    }
}
