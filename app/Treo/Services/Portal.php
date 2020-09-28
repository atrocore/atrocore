<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;
use Treo\Core\Application as App;

/**
 * Portal service
 *
 * @author r.ratsun <r.ratsun@zinitsolutions.com>
 */
class Portal extends \Espo\Services\Record
{
    /**
     * @var null|array
     */
    protected $urls = null;

    /**
     * @param Entity $entity
     */
    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        $this->setUrl($entity);
    }

    /**
     * @param Entity $entity
     */
    public function loadAdditionalFieldsForList(Entity $entity)
    {
        parent::loadAdditionalFieldsForList($entity);

        $this->setUrl($entity);
    }

    /**
     * Set url
     *
     * @param Entity $entity
     */
    protected function setUrl(Entity $entity): void
    {
        if (!empty($url = $this->getUrls()[$entity->get('id')])) {
            $entity->set('url', $url);
        }
    }

    /**
     * Get urls
     *
     * @return array
     */
    protected function getUrls(): array
    {
        if (is_null($this->urls)) {
            $this->urls = App::getPortalUrlFileData();
        }

        return $this->urls;
    }
}
