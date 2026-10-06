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
        $this->custom        = $custom;
    }

    /**
     * Returns the file content as base64. When $params are given (format, width, height, quality, scale),
     * the image is converted first, in memory. With $dataUri the result is a "data:<mime>;base64,..." string.
     * Returns false when the current user has no read access to the file.
     */
    public function run(Entity $file, array $params = [], bool $dataUri = false)
    {
        try {
            if (!$this->canRead($file)) {
                $GLOBALS['log']->warning('convertFileToBase64: no read access to ' . $file->getEntityName() . ' ' . $file->get('id') . '.');

                return false;
            }

            if (empty($params)) {
                $content  = $this->entityManager->getRepository('File')->getContents($file);
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

    /**
     * The acl service is resolved on every call because it follows the current user,
     * which changes while jobs and actions run. Without a user the access is denied.
     */
    protected function canRead(Entity $file): bool
    {
        try {
            return (bool)$this->entityManager->getContainer()->get('acl')->checkEntity($file, 'read');
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function sanitizeMimeType(string $mimeType): string
    {
        return preg_match('#^[A-Za-z0-9][\w.+-]*/[A-Za-z0-9][\w.+-]*$#', $mimeType) ? $mimeType : 'application/octet-stream';
    }
}
