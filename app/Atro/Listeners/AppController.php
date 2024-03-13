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

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;
use Espo\ORM\Entity;
use stdClass;

/**
 * Class AppController
 */
class AppController extends AbstractListener
{
    /**
     * After action user
     * Change language and Hide dashlets
     *
     * @param Event $event
     *
     * @throws \Atro\Core\Exceptions\Error
     */
    public function afterActionUser(Event $event)
    {
        $result = $event->getArgument('result');
        $language = $event->getArgument('request')->get('language');
        $currentLanguage = $result['language'] ?? '';

        if (!empty($result['preferences'])) {
            $this->hideDashletsWithEmptyEntity($result['preferences']);
        }

        if (!empty($result['user']) && !empty($language) && $currentLanguage !== $language) {
            /** @var \Espo\Listeners\Entity $preferences */
            $preferences = $this->getPreferences();

            // change language for user
            $preferences->set('language', $language);

            $result['language'] = $language;

            $this->saveEntity($preferences);
        }
        $event->setArgument('result', $result);
    }

    /**
     * Save entity
     *
     * @param \Espo\Listeners\Entity $entity
     */
    protected function saveEntity(Entity $entity): void
    {
        $this->getEntityManager()->saveEntity($entity);
    }

    /**
     * Hide dashlets with empty entity
     *
     * @param stdClass $preferences
     *
     * @throws \Atro\Core\Exceptions\Error
     */
    protected function hideDashletsWithEmptyEntity(stdClass &$preferences): void
    {
        $dashletsOptions = isset($preferences->dashletsOptions) ? $preferences->dashletsOptions : null;

        if (!empty($dashletsOptions)) {
            $dashboards = isset($preferences->dashboardLayout) ? $preferences->dashboardLayout : [];
            foreach ($dashboards as $dashboard) {//iterate over dashboard
                if (is_array($dashboard->layout)) {
                    foreach ($dashboard->layout as $key => $layout) {//iterate over layout of dashboard
                        $id = $layout->id;
                        //check isset dashlet with this ID layout
                        $issetDashlet = isset($dashletsOptions->{$id}) && is_object($dashletsOptions->{$id});
                        $isEntity = !empty($dashletsOptions->{$id}->entityType)
                            && $this->isExistEntity($dashletsOptions->{$id}->entityType);
                        if ($issetDashlet && !$isEntity) {
                            //hide dashlet
                            unset($dashletsOptions->{$id});
                            if (isset($dashboard->layout[$key])) {
                                unset($dashboard->layout[$key]);
                            }
                        }
                    }
                    //reset key in array
                    $dashboard->layout = array_values($dashboard->layout);
                }
            }
        }
    }

    /**
     * @param string|null $name
     *
     * @return bool
     */
    protected function isExistEntity(?string $name): bool
    {
        $isEntity = false;
        if (!empty($name)) {
            $isEntity = class_exists($this->getEntityManager()->normalizeEntityName($name));
        }

        return $isEntity;
    }
}
