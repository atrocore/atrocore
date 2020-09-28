<?php

namespace Espo\Repositories;

use Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;

class Portal extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        parent::init();
        $this->addDependency('config');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    public function loadUrlField(Entity $entity)
    {
        if ($entity->get('customUrl')) {
            $entity->set('url', $entity->get('customUrl'));
            return;
        }
        $siteUrl = $this->getConfig()->get('siteUrl');
        $siteUrl = rtrim($siteUrl , '/') . '/';
        $url = $siteUrl . 'portal/';
        if ($entity->id === $this->getConfig()->get('defaultPortalId')) {
            $entity->set('isDefault', true);
            $entity->setFetched('isDefault', true);
        } else {
            if ($entity->get('customId')) {
                $url .= $entity->get('customId') . '/';
            } else {
                $url .= $entity->id . '/';
            }
            $entity->setFetched('isDefault', false);
        }
        $entity->set('url', $url);
    }

    protected function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        if ($entity->has('isDefault')) {
            if ($entity->get('isDefault')) {
                $this->getConfig()->set('defaultPortalId', $entity->id);
                $this->getConfig()->save();
            } else {
                if ($entity->isAttributeChanged('isDefault')) {
                    $this->getConfig()->set('defaultPortalId', null);
                    $this->getConfig()->save();
                }
            }
        }
    }
}

