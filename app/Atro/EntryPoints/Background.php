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

namespace Atro\EntryPoints;

use Atro\Core\DataManager;
use Atro\Core\Utils\Util;

class Background extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public function run()
    {
        $this->setBackground();

        $content = file_get_contents($_SESSION['background']['imagePath']);

        header('Content-Disposition:inline;filename="' . $_SESSION['background']['imageName'] . '"');
        header('Content-Type: ' . mime_content_type($_SESSION['background']['imagePath']));
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        header('Content-Length: ' . mb_strlen($content, "8bit"));

        echo $content;
        exit;
    }

    public function setBackground(): void
    {
        $timestamp = DataManager::getPublicData('dataTimestamp');

        session_start();
        if (
            !isset($_SESSION['background'])
            || $_SESSION['background']['till'] < new \DateTime()
            || !file_exists($_SESSION['background']['imagePath'])
            || $_SESSION['background']['dataTimestamp'] !== $timestamp
        ) {
            $_SESSION['background'] = $this->getBackground();
            $_SESSION['background']['dataTimestamp'] = $timestamp;
            $_SESSION['background']['till'] = (new \DateTime())->modify('+2 hours');
        }
    }

    protected function getBackground(): array
    {
        if ($this->getConfig()->get('isInstalled')) {
            $backgrounds = $this->getConfig()->get('referenceData.Background', []);
            if (!empty($backgrounds)) {
                $collection = $this->getEntityManager()->getRepository('File')
                    ->where(['id' => array_column($backgrounds, 'imageId')])
                    ->find();

                if (!empty($collection)) {
                    $files = iterator_to_array($collection, false);
                    while (!empty($files[0])) {
                        $index = rand(0, count($files) - 1);
                        $file = $files[$index];
                        array_splice($files, $index, 1);

                        try {
                            $imagePath = $file->findOrCreateLocalFilePath('data/.backgrounds');
                            return [
                                'imageName'  => $file->get('name'),
                                'imagePath'  => $imagePath,
                                'authorName' => '',
                                'authorLink' => '',
                            ];
                        } catch (\Throwable $exception) {
                            $GLOBALS['log']->error('Background error for file ' . $file->get('id') . ': ' . $exception->getMessage());
                        }
                    }
                }
            }
        }

        $imagesPath = 'client/img/background';
        if (!file_exists($imagesPath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        $images = Util::scanDir($imagesPath);
        $imageName = $images[array_rand($images)];
        $imagePath = $imagesPath . '/' . $imageName;

        $imageMetadata = \exif_read_data($imagePath);

        return [
            'imageName'  => $imageName,
            'imagePath'  => $imagePath,
            'authorName' => $imageMetadata['Artist'] ?? '',
            'authorLink' => $imageMetadata['COMPUTED']['UserComment'] ?? ''
        ];
    }
}
