<?php

namespace Espo\EntryPoints;

use Espo\Core\Exceptions\NotFound;
use Treo\EntryPoints\Image;

class LogoImage extends Image
{
    public static $authRequired = false;

    public function run()
    {
        if (!empty($_GET['id'])) {
            $id = $_GET['id'];
        } else {
            $id = $this->getConfig()->get('companyLogoId');
        }

        if (empty($id)) {
            throw new NotFound();
        }

        $size = null;
        if (!empty($_GET['size'])) {
            $size = $_GET['size'];
        }

        $this->show($id, $size);
    }

    /**
     * @inheritDoc
     */
    protected function getImageSize(string $size): ?array
    {
        if ($size == 'small-logo') {
            return [181, 44];
        }

        return parent::getImageSize($size);
    }
}

