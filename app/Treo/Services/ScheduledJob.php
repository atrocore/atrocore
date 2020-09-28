<?php

declare(strict_types=1);

namespace Treo\Services;

use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;
use Cron\CronExpression;

/**
 * ScheduledJob service
 *
 * @author r.ratsun@zinitsolutions.com
 */
class ScheduledJob extends \Espo\Core\Templates\Services\Base
{
    /**
     * @param Entity $entity
     * @param        $data
     *
     * @throws Error
     */
    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeCreateEntity($entity, $data);
    }

    /**
     * @param Entity $entity
     * @param        $data
     *
     * @throws Error
     */
    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        $this->isScheduledValid($entity);

        parent::beforeUpdateEntity($entity, $data);
    }

    /**
     * Is scheduled valid
     *
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isScheduledValid(Entity $entity): bool
    {
        if (!empty($entity->get('scheduling'))) {
            try {
                $cronExpression = CronExpression::factory($entity->get('scheduling'));
            } catch (\Exception $e) {
                // prepare key
                $key = 'Wrong crontab configuration';

                // prepare message
                $message = $this
                    ->getInjection('language')
                    ->translate($key, 'exceptions', 'ScheduledJob');

                throw new Error($message);
            }
        }

        return true;
    }

    /**
     * Init
     */
    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
