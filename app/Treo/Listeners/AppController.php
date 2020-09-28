<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Espo\Entities\Preferences;
use Espo\ORM\Entity;
use stdClass;
use Treo\Core\EventManager\Event;

/**
 * Class AppController
 *
 * @author r.ratsun <r.ratsun@gmail.com>
 */
class AppController extends AbstractListener
{
    /**
     * After action user
     * Change language and Hide dashlets
     *
     * @param Event $event
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    public function afterActionUser(Event $event)
    {
        $result = $event->getArgument('result');
        $language = $event->getArgument('request')->get('language');
        $currentLanguage = $result['language'] ?? '';

        $this->hideDashletsWithEmptyEntity($result['preferences']);

        if (!empty($result['user']) && !empty($language) && $currentLanguage !== $language) {
            /** @var Entity $preferences */
            $preferences = $this->getPreferences();

            // change language for user
            $preferences->set('language', $language);

            $result['language'] = $language;

            $this->saveEntity($preferences);
        }
        $event->setArgument('result', $result);
    }

    /**
     * Get preferences
     *
     * @return Preferences
     */
    protected function getPreferences(): Preferences
    {
        return $this->getContainer()->get('Preferences');
    }

    /**
     * Save entity
     *
     * @param Entity $entity
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
     * @throws \Espo\Core\Exceptions\Error
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
