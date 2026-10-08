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
use Atro\Core\Utils\Config;

/**
 * Returns base64 content of a converted image, given its URL (as returned by getConvertedImageUrl).
 * Only URLs of this site that point into the rendition directory are supported; the file is read
 * from disk, nothing is requested over the network.
 *
 * @deprecated Use convertFileToBase64(file, params) instead. Will be removed in 3.0.0.
 */
class ConvertFileToBase64FromUrl extends AbstractTwigFunction
{
    protected static array $logged = [];

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function run(string $urlOrPath, ?string $type = null)
    {
        $this->logOnce('deprecated', 'convertFileToBase64FromUrl is deprecated, use convertFileToBase64(file, params) instead.');

        if (!empty($type) && !preg_match('#^[A-Za-z0-9][\w.+-]*/[A-Za-z0-9][\w.+-]*$#', $type)) {
            return false;
        }

        $path = $this->resolveRenditionFile($urlOrPath);
        if ($path === null) {
            $this->logOnce('unsupported', 'convertFileToBase64FromUrl only supports URLs of converted images of this site (as returned by getConvertedImageUrl), use convertFileToBase64(file, params) instead.');

            return false;
        }

        $content = file_get_contents($path);
        if (empty($content)) {
            return false;
        }

        $data = base64_encode($content);

        if (!empty($type)) {
            $data = 'data:' . $type . ';base64,' . $data;
        }

        return $data;
    }

    protected function logOnce(string $key, string $message): void
    {
        if (!isset(self::$logged[$key])) {
            self::$logged[$key] = true;
            $GLOBALS['log']->warning($message);
        }
    }

    protected function resolveRenditionFile(string $url): ?string
    {
        $site = parse_url((string)$this->config->get('siteUrl'));
        $parts = parse_url($url);

        if (empty($site['host']) || !is_array($parts) || empty($parts['host']) || empty($parts['path'])) {
            return null;
        }

        if (!in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }

        if (strtolower($parts['host']) !== strtolower($site['host']) || ($parts['port'] ?? null) !== ($site['port'] ?? null)) {
            return null;
        }

        $sitePath = rtrim($site['path'] ?? '', '/');
        if ($sitePath !== '' && !str_starts_with($parts['path'], $sitePath . '/')) {
            return null;
        }

        $path = ltrim(substr($parts['path'], strlen($sitePath)), '/');

        $renditionDir = realpath('public' . DIRECTORY_SEPARATOR . trim((string)$this->config->get('renditionPath', 'upload/rendition/'), '/'));
        if ($renditionDir === false) {
            return null;
        }

        foreach (array_unique([$path, rawurldecode($path)]) as $candidate) {
            if ($candidate === '' || str_contains($candidate, "\0")) {
                continue;
            }

            $real = realpath('public' . DIRECTORY_SEPARATOR . $candidate);
            if ($real !== false && is_file($real) && str_starts_with($real, $renditionDir . DIRECTORY_SEPARATOR)) {
                return $real;
            }
        }

        return null;
    }
}
