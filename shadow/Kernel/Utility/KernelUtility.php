<?php

declare(strict_types=1);

namespace Shadow\Kernel\Utility;

use Shared\MimimalCmsConfig;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

class KernelUtility
{
    /**
     * Returns the current request path.
     *
     * @param ?string $urlRoot [optional] The root of the URL. Default is `MimimalCmsConfig::$urlRoot`.
     * 
     * @return string The current request path.
     *
     * * **Example :** Output: `/home`
     */
    static public function getCurrentUri(?string $urlRoot = null): string
    {
        $urlRoot = $urlRoot ?? MimimalCmsConfig::$urlRoot;

        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($urlRoot === '') {
            return $requestUri;
        }

        if ($urlRoot === $requestUri || "{$urlRoot}/" === $requestUri) {
            return '/';
        }

        $replaced = preg_replace("{^{$urlRoot}(/.*)}", '$1', $requestUri);
        return $replaced;
    }
}
