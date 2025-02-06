<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\ModuleManager;

use Atro\Core\Container;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Class AfterInstallAfterDelete
 */
class AfterInstallAfterDelete
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * AbstractEvent constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * After module install event
     */
    public function afterInstall(): void
    {
    }

    /**
     * After module delete event
     */
    public function afterDelete(): void
    {
    }

    protected  function addMenuItems(array $menuItems): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get('connection');

        $defaultLayout = $connection->createQueryBuilder()
            ->select('id, navigation')
            ->from('layout_profile')
            ->where('is_default = :true')
            ->andWhere('deleted = :false')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAssociative();

        if(empty($defaultLayout)) {
            return;
        }

        $navigation = @json_decode($defaultLayout['navigation'], true) ?? [];

        $tabList = [];
        foreach ($navigation as $item) {
            if(is_array($item) && !empty($item['items'])) {
                foreach ($item['items'] as $subItem) {
                    $tabList[] = $subItem;
                }
            }else{
                $tabList[] = $item;
            }
        }

        foreach ($menuItems as $item) {
            if(is_array($item) && !empty($item['items'])) {
                $subItems = [];
                foreach ($item['items'] as $subItem) {
                    if(!in_array($subItem, $tabList)) {
                        $subItems[] = $subItem;
                    }
                }
                if(!empty($subItems)) {
                    $item['items'] = $subItems;
                    $navigation[] = $item;
                }
            }else if(!in_array($item, $tabList)) {
                $navigation[] = $item;
            }
        }

        $connection->createQueryBuilder()
            ->update('layout_profile')
            ->set('navigation', ':navigation')
            ->where('id =:id')
            ->setParameter('navigation', json_encode($navigation))
            ->setParameter('id', $defaultLayout['id'])
            ->executeStatement();
    }
    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }
}
