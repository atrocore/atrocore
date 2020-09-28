<?php
declare(strict_types=1);

namespace Treo\Core\Loaders;

use Treo\Core\Utils\Language;

/**
 * BaseLanguage loader
 *
 * @author r.ratsun@treolabs.com
 */
class BaseLanguage extends Base
{
    /**
     * Load BaseLanguage
     *
     * @return Language
     */
    public function load()
    {
        $language = new Language(
            'en_US',
            $this->getContainer()->get('fileManager'),
            $this->getContainer()->get('metadata'),
            $this->getContainer()->get('config')->get('useCache')
        );
        $language->setEventManager($this->getContainer()->get('eventManager'));

        return $language;
    }
}
