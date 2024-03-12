<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

namespace Atro\EntryPoints;

use Espo\Core\Utils\Util;

class Background extends AbstractEntryPoint
{
    public static bool $authRequired = false;

    public static function setBackground(): void
    {
        $imagesPath = 'client/img/background';
        if (!file_exists($imagesPath)) {
            header("HTTP/1.0 404 Not Found");
            exit;
        }

        session_start();
        if (!isset($_SESSION['background']) || $_SESSION['background']['till'] < new \DateTime() || !file_exists($_SESSION['background']['imagePath'])) {
            $images = Util::scanDir($imagesPath);

            $_SESSION['background']['till'] = (new \DateTime())->modify('+2 hours');
            $_SESSION['background']['imageName'] = $images[array_rand($images)];
            $_SESSION['background']['imagePath'] = $imagesPath . '/' . $_SESSION['background']['imageName'];

            $imageMetadata = \exif_read_data($_SESSION['background']['imagePath']);

            $_SESSION['background']['authorName'] = isset($imageMetadata['Artist']) ? $imageMetadata['Artist'] : '';
            $_SESSION['background']['authorLink'] = isset($imageMetadata['COMPUTED']['UserComment']) ? $imageMetadata['COMPUTED']['UserComment'] : '';
        }
    }

    public function run()
    {
        self::setBackground();

        $content = file_get_contents($_SESSION['background']['imagePath']);

        header('Content-Disposition:inline;filename="' . $_SESSION['background']['imageName'] . '"');
        header('Content-Type: ' . mime_content_type($_SESSION['background']['imagePath']));
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        header('Content-Length: ' . mb_strlen($content, "8bit"));

        echo $content;
        exit;
    }
}
