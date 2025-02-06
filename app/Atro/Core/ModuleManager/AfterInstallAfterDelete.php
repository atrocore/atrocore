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

    protected  function addNavigationItems($menuItems): void
    {
        $this->updateLayoutProfileNavigation($menuItems);
    }

    protected  function removeNavigationItems($menuItems): void
    {
        $this->updateLayoutProfileNavigation($menuItems, true);
    }

    /**
     * @return Container
     */
    protected function getContainer(): Container
    {
        return $this->container;
    }

    private  function updateLayoutProfileNavigation(array $menuItems, bool $shouldRemove = false): void
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

        $navigation = $shouldRemove ? $this->removeItems($menuItems, $navigation): $this->addItems($menuItems, $navigation);

        $connection->createQueryBuilder()
            ->update('layout_profile')
            ->set('navigation', ':navigation')
            ->where('id =:id')
            ->setParameter('navigation', json_encode($navigation))
            ->setParameter('id', $defaultLayout['id'])
            ->executeStatement();
    }

    private  function addItems(array $menuItems, array $navigation): array
    {
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
            if(is_array($item) && !empty($item['id']) && !empty($item['items'])) {

                $subItems = [];
                foreach ($item['items'] as $subItem) {
                    if(!in_array($subItem, $tabList)) {
                        $subItems[] = $subItem;
                    }
                }

                if(!empty($subItems)) {
                    $item['items'] = $subItems;
                    foreach ($navigation as $key => $navigationItem) {
                        if(!empty($navigationItem['id']) && $navigationItem['id'] === $item['id']) {
                           $navigation[$key]['items'] = array_merge($navigationItem['items'], $subItems);
                           $alreadyAdded = true;
                            break;
                        }
                    }

                    if(empty($alreadyAdded)) {
                        $navigation[] = $item;
                    }
                }

            }else if(!in_array($item, $tabList)) {
                $navigation[] = $item;
            }
        }
        return $navigation;
    }

    private  function removeItems(array $menuItems, array $navigation): array
    {
        foreach ($menuItems as $item) {
            if(is_string($item)) {
                if(in_array($item, $navigation)) {
                    $navigation = array_filter($navigation, fn($i) => $i !== $item);
                    continue;
                }

                foreach ($navigation as $key => $navigationItem) {
                    if(is_array($navigationItem) && !empty($navigationItem['items'])) {
                        if(in_array($item, $navigationItem['items'])) {
                            $navigation[$key]['items'] = array_filter($navigationItem['items'], fn($i) => $i !== $item);
                            if(empty($navigation[$key]['items'])) {
                                unset($navigation[$key]);
                            }
                        }
                    }
                }
            }
        }

        return $navigation;
    }
}
