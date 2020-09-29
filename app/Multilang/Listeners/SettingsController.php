<?php

declare(strict_types=1);

namespace Multilang\Listeners;

use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class SettingsController
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class SettingsController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function afterActionUpdate(Event $event): void
    {
        // regenerate multilang fields
        if (isset($event->getArgument('data')->inputLanguageList) || !empty($event->getArgument('data')->isMultilangActive)) {
            $this->getContainer()->get('dataManager')->rebuild();
        }
    }
}
