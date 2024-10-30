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

use Atro\Core\Download\Custom;
use Atro\Core\Twig\AbstractTwigFunction;
use Atro\Core\Utils\Config;
use Atro\Entities\File;
use Espo\ORM\EntityManager;

class GetConvertedImageUrl extends AbstractTwigFunction
{
    protected Custom $custom;
    protected Config $config;

    public function __construct(Custom $custom, Config $config)
    {
        $this->custom = $custom;
        $this->config = $config;
    }

    public function run(File $file, array $params = []): bool|string
    {
        if (!str_contains($file->get('mimeType'), 'image')) {
            return false;
        }

        if (empty($params)) {
            $params = [
                "quality" => 100,
                "format" => "jpg",
            ];
        }

        try {
            return $this->config->get('siteUrl') . '/' . $this->custom->convert($file, $params);
        } catch (\Throwable $e) {
            return false;
        }
    }
}