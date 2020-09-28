<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Language as Instance;

/**
 * DefaultLanguage loader
 *
 * @author r.ratsun@treolabs.com
 */
class DefaultLanguage extends Base
{
    /**
     * Load DefaultLanguage
     *
     * @return Instance
     */
    public function load()
    {
        $language = new Instance(
            Instance::detectLanguage($this->getContainer()->get('config')),
            $this->getContainer()->get('fileManager'),
            $this->getContainer()->get('metadata'),
            $this->getContainer()->get('config')->get('useCache')
        );
        $language->setEventManager($this->getContainer()->get('eventManager'));

        return $language;
    }
}
