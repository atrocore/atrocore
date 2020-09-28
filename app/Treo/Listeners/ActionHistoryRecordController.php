<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\Utils\Metadata;
use Treo\Core\EventManager\Event;

/**
 * Class ActionHistoryRecordController
 *
 * @author r.ratsun@treolabs.com
 */
class ActionHistoryRecordController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeActionList(Event $event)
    {
        // get where
        $where = $event->getArgument('request')->get('where', []);

        // get scopes
        $scopes = $this
            ->getMetadata()
            ->get('scopes');

        // prepare where
        $where[] = [
            'type'      => 'in',
            'attribute' => 'targetType',
            'value'     => array_keys($scopes)
        ];

        // set where
        $event->getArgument('request')->setQuery('where', $where);
    }

    /**
     * Get metadata
     *
     * @return Metadata
     */
    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
