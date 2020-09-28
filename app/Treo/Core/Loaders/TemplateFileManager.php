<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class TemplateFileManager
 *
 * @author r.ratsun@gmail.com
 */
class TemplateFileManager extends Base
{
    /**
     * @inheritDoc
     */
    public function load()
    {
        $templateFileManager = new \Espo\Core\Utils\TemplateFileManager(
            $this->getContainer()->get('config'),
            $this->getContainer()->get('metadata')
        );

        return $templateFileManager;
    }
}
