<?php

namespace Espo\EntryPoints;

use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use Treo\Core\EntryPoints\AbstractEntryPoint;

class Attachment extends AbstractEntryPoint
{
    public static $authRequired = true;

    public function run()
    {
        $id = $_GET['id'];
        if (empty($id)) {
            throw new BadRequest();
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $id);

        if (!$attachment) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($attachment)) {
            throw new Forbidden();
        }

        $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);

        if (!file_exists($fileName)) {
            throw new NotFound();
        }

        if ($attachment->get('type')) {
            header('Content-Type: ' . $attachment->get('type'));
        }

        header('Pragma: public');
        header('Content-Length: ' . filesize($fileName));
        readfile($fileName);
        exit;
    }
}

