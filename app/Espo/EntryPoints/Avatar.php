<?php

namespace Espo\EntryPoints;

use Espo\Core\Exceptions\BadRequest;
use Treo\EntryPoints\Image;

class Avatar extends Image
{
    public static $authRequired = true;

    public static $notStrictAuth = true;

    protected $systemColor = [212,114,155];

    protected $colorList = [
        [111,168,214],
        [237,197,85],
        [212,114,155],
        '#8093BD',
        [124,196,164],
        [138,124,194],
        [222,102,102],
        '#ABE3A1',
        '#E8AF64',
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

        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user) {
            header('Content-Type: image/png');
            $img  = imagecreatetruecolor(14, 14);
            imagesavealpha($img, true);
            $color = imagecolorallocatealpha($img, 127, 127, 127, 127);
            imagefill($img, 0, 0, $color);
            imagepng($img);
            imagecolordeallocate($img, $color);
            imagedestroy($img);
            exit;
        }

        $id = $user->get('avatarId');

        $size = null;
        if (!empty($_GET['size'])) {
            $size = $_GET['size'];
        }

        if (!empty($id)) {
            $this->show($id, $size, true);
        } else {
            $identicon = new \Identicon\Identicon();
            if (empty($size)) {
                $size = 'small';
            }
            if (!empty($data = $this->getImageSize($size))) {
                $width = $data[0];

                header('Cache-Control: max-age=360000, must-revalidate');
                header('Content-Type: image/png');

                $hash = $userId;
                $color = $this->getColor($userId);
                if ($hash === 'system') {
                    $color = $this->systemColor;
                }

                $imgContent = $identicon->getImageData($hash, $width, $color);
                echo $imgContent;
                exit;
            }
        }
    }

}

