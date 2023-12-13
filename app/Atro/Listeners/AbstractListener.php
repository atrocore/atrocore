<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\Container;
use Espo\Core\CronManager;
use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Preferences;
use Espo\Entities\User;

abstract class AbstractListener
{
    protected Container $container;

    protected array $services = [];

    public function setContainer(Container $container): AbstractListener
    {
        $this->container = $container;

        return $this;
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getService(string $name)
    {
        if (!isset($this->services[$name])) {
            $this->services[$name] = $this->getContainer()->get('serviceFactory')->create($name);
        }

        return $this->services[$name];
    }

    protected function getEntityManager(): EntityManager
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getLanguage(): Language
    {
        if (is_null($this->getContainer()->get('preferences'))) {
            throw new Error('No preferences defined');
        }

        return $this->getContainer()->get('language');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }

    protected function getUser(): User
    {
        return $this->getContainer()->get('user');
    }

    protected function getPreferences(): Preferences
    {
        return $this->getContainer()->get('Preferences');
    }

    public function createJob(string $name, string $scheduling, string $serviceName, string $methodName): void
    {
        $cronExpression = \Cron\CronExpression::factory($scheduling);
        $nextDate = $cronExpression->getNextRunDate()->format('Y-m-d H:i:s');

        $existingJob = $this->getEntityManager()->getRepository('Job')
            ->where([
                'serviceName' => $serviceName,
                'methodName'  => $methodName,
                'executeTime' => $nextDate,
            ])
            ->findOne();

        if (!empty($existingJob)) {
            return;
        }

        $jobEntity = $this->getEntityManager()->getEntity('Job');
        $jobEntity->set([
            'name'        => $name,
            'status'      => CronManager::PENDING,
            'serviceName' => $serviceName,
            'methodName'  => $methodName,
            'executeTime' => $nextDate
        ]);
        $this->getEntityManager()->saveEntity($jobEntity);
    }
}
