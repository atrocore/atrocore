<?php

namespace Atro\Listeners;

use Atro\Core\EventManager\Event;

class SettingsController extends AbstractListener
{
    public function afterActionRead(Event $event)
    {
        $result = $event->getArgument('result');

        $defaultLayout = $this->getEntityManager()->getRepository('LayoutProfile')->where(['isDefault' => true])->findOne();

        $result['lpNavigation'] = $this->prepareNavigation($defaultLayout->get('navigation') ?? []);

        $event->setArgument('result', $result);
    }


    protected function prepareNavigation(array $navigation): array
    {
            $metadata = $this->getContainer()->get('metadata');

            $newNavigation = [];
            foreach ($navigation as $item) {
                if (is_string($item)) {
                    if ($metadata->get("scopes.$item.tab")) {
                        $newNavigation[] = $item;
                    }
                } else {
                    if (!empty($item->items)) {
                        $newSubItems = [];
                        foreach ($item->items as $subItem) {
                            if ($metadata->get("scopes.$subItem.tab")) {
                                $newSubItems[] = $subItem;
                            }
                        }
                        $item->items = $newSubItems;
                    }
                    $newNavigation[] = $item;
                }
            }

        return $newNavigation;
    }
}