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
use Atro\Entities\File;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class ConvertFileToBase64 extends AbstractTwigFunction
{
    protected EntityManager $entityManager;
    protected Custom $custom;

    public function __construct(EntityManager $entityManager, Custom $custom)
    {
        $this->entityManager = $entityManager;
        $this->custom = $custom;
    }

    /**
     * Returns the file content as base64. When $params are given (format, width, height, quality, scale),
     * the image is converted first, in memory. With $dataUri the result is a "data:<mime>;base64,..." string.
     */
    public function run(Entity $file, array $params = [], bool $dataUri = false)
    {
        try {
            if (empty($params)) {
                $content = $this->entityManager->getRepository('File')->getContents($file);
                $mimeType = $this->sanitizeMimeType((string)$file->get('mimeType'));
            } else {
                if (!$file instanceof File || !str_contains((string)$file->get('mimeType'), 'image')) {
                    return false;
                }

                ['content' => $content, 'mimeType' => $mimeType] = $this->custom->convertToBlob($file, $params);
                $mimeType = $this->sanitizeMimeType($mimeType);
            }

            $data = base64_encode($content);

            return $dataUri ? 'data:' . $mimeType . ';base64,' . $data : $data;
        } catch (\Throwable $e) {
            if (!empty($params)) {
                $GLOBALS['log']->warning('convertFileToBase64: image conversion failed: ' . $e->getMessage());
            }

            return false;
        }
    }

    protected function sanitizeMimeType(string $mimeType): string
    {
        return preg_match('#^[A-Za-z0-9][\w.+-]*/[A-Za-z0-9][\w.+-]*$#', $mimeType) ? $mimeType : 'application/octet-stream';
    }
}
