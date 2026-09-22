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

use Atro\Entities\File;
use Atro\Core\Exceptions\BadRequest;
use Atro\Entities\User;

class Avatar extends Image
{
    public static bool $authRequired = true;

    public static bool $notStrictAuth = true;

    protected string $systemColor = '#D4729B';

    protected array $colorList
        = [
            "#6FA8D6",
            "#EDC555",
            "#D4729B",
            "#8093BD",
            "#7CC4A4",
            "#8A7CC2",
            "#DE6666",
            "#ABE3A1",
            "#E8AF64"
        ];

    protected function getColor($hash)
    {
        $length = strlen($hash);
        $sum = 0;
        for ($i = 0; $i < $length; $i++) {
            $sum += ord($hash[$i]);
        }
        $x = intval($sum % 128) + 1;

        $index = intval($x * count($this->colorList) / 128);
        return $this->colorList[$index];
    }

    public function run()
    {
        if (empty($_GET['id'])) {
            throw new BadRequest();
        }

        $userId = $_GET['id'];

        /* @var User $user */
        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user) {
            header('Content-Type: image/png');
            $img = imagecreatetruecolor(14, 14);
            imagesavealpha($img, true);
            $color = imagecolorallocatealpha($img, 127, 127, 127, 127);
            imagefill($img, 0, 0, $color);
            imagepng($img);
            imagecolordeallocate($img, $color);
            imagedestroy($img);
            exit;
        }

        if ($user->hasAvatar()) {
            $this->showAvatar($user);
        } else {
            $avatar = new \LasseRafn\InitialAvatarGenerator\InitialAvatar();

            if (empty($size)) {
                $size = 'small';
            }
            if (!empty($data = $this->getImageSize($size))) {
                $width = $data[0];

                header('Cache-Control: max-age=360000, must-revalidate');
                header('Content-Type: image/png');

                $hash = $userId;
                $color = $this->getColor($userId);
                if ($hash === $this->getConfig()->get('systemUserId')) {
                    $color = $this->systemColor;
                }

                $contents = $avatar
                    ->name($user->get('name'))
                    ->size($width)
                    ->background($color)
                    ->color('#fff')
                    ->generate()
                    ->stream('png', 100);

                echo $contents; // nosemgrep: php.lang.security.injection.echoed-request.echoed-request

                exit;
            }
        }
    }

    protected function checkFile(File $file): bool
    {
        return true;
    }

    protected function showAvatar(User $user): void
    {
        $contents = $user->getAvatarContent();
        $mimeType = $this->getEntityManager()->getRepository('User')->getAvatarMimeType($user);

        header('Content-Disposition:inline;filename="' . $user->getAvatarName() . '"');
        if (!empty($mimeType)) {
            header('Content-Type: ' . $mimeType);
        }
        header('Pragma: public');
        header('Cache-Control: max-age=360000, must-revalidate');
        header('Content-Length: ' . mb_strlen($contents, '8bit'));
        echo $contents;
        exit;
    }
}
