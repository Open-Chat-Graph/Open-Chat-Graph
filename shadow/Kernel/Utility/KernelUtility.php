<?php

declare(strict_types=1);

namespace Shadow\Kernel\Utility;

/**
 * @author mimimiku778 <0203.sub@gmail.com>
 * @license https://github.com/mimimiku778/MimimalCMS/blob/master/LICENSE.md
 */

class KernelUtility
{
    /**
     * Returns the current request path.
     *
     * @param string $urlRoot [optional] The root of the URL. Default is the constant URL_ROOT.
     * 
     * @return string The current request path.
     *
     * * **Example :** Output: `/home`
     */
    static public function getCurrentUri(string $urlRoot = URL_ROOT): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if ($urlRoot === '') {
            return $requestUri;
        }

        if (preg_match("{^{$urlRoot}$}", $requestUri)) {
            return '/';
        }
        
        $replaced = preg_replace("{^{$urlRoot}(/.*)}", '$1', $requestUri);
        return $replaced;
    }
}
