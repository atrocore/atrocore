<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;

class ConvertFileToBase64FromUrl extends AbstractTwigFunction
{
    public function run(string $url, ?string $type = null)
    {
        $content = file_get_contents($this->normalizeUrl($url));

        if(empty($content)){
            return false;
        }

        $data = base64_encode($content);

        if($type){
            $data = 'data:'. $type . ';base64,' . $data;
        }

        return $data;
    }

    protected  function normalizeUrl($url) {
        // Parse the URL
        $parts = parse_url($url);

        if ($parts === false) {
            return false; // Invalid URL
        }

        // Ensure scheme is present
        if (!isset($parts['scheme'])) {
            $parts['scheme'] = 'http';
        }

        // Normalize scheme and host to lowercase
        $parts['scheme'] = strtolower($parts['scheme']);
        if (isset($parts['host'])) {
            $parts['host'] = strtolower($parts['host']);
        }

        // Encode the path
        if (isset($parts['path'])) {
            $parts['path'] = implode('/', array_map('rawurlencode', explode('/', $parts['path'])));
        }

        // Encode query string
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            $parts['query'] = http_build_query($query);
        }

        // Rebuild the URL
        $normalizedUrl = $parts['scheme'] . '://';
        if (isset($parts['user']) && isset($parts['pass'])) {
            $normalizedUrl .= $parts['user'] . ':' . $parts['pass'] . '@';
        } elseif (isset($parts['user'])) {
            $normalizedUrl .= $parts['user'] . '@';
        }
        $normalizedUrl .= $parts['host'];
        if (isset($parts['port'])) {
            $normalizedUrl .= ':' . $parts['port'];
        }
        if (isset($parts['path'])) {
            $normalizedUrl .= $parts['path'];
        }
        if (isset($parts['query'])) {
            $normalizedUrl .= '?' . $parts['query'];
        }
        if (isset($parts['fragment'])) {
            $normalizedUrl .= '#' . $parts['fragment'];
        }

        return $normalizedUrl;
    }

}