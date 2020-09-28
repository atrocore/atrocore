<?php

declare(strict_types=1);

namespace Treo\Listeners;

use Treo\Core\EventManager\Event;

/**
 * Class EntityManagerController
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class EntityManagerController extends AbstractListener
{
    /**
     * @var array
     */
    protected $scopesConfig = null;

    /**
     * @param Event $event
     */
    public function afterActionCreateEntity(Event $event)
    {
        // update scopes
        $this->updateScope(get_object_vars($event->getArgument('data')));

        if ($event->getArgument('result')) {
            $this->getContainer()->get('dataManager')->rebuild();
        }
    }

    /**
     * @param Event $event
     */
    public function afterActionUpdateEntity(Event $event)
    {
        $this->afterActionCreateEntity($event);
    }

    /**
     * Set data to scope
     *
     * @param array $data
     */
    protected function updateScope(array $data): void
    {
        // prepare name
        $name = trim(ucfirst($data['name']));

        $this
            ->getContainer()
            ->get('metadata')
            ->set('scopes', $name, $this->getPreparedScopesData($data));

        // save
        $this->getContainer()->get('metadata')->save();
    }

    /**
     * Get prepared scopes data
     *
     * @param array $data
     *
     * @return array
     */
    protected function getPreparedScopesData(array $data): array
    {
        // prepare result
        $scopeData = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->getScopesConfig()['edited'])) {
                $scopeData[$key] = $value;
            }
        }

        return $scopeData;
    }

    /**
     * Get scopes config
     *
     * @return array
     */
    protected function getScopesConfig(): array
    {
        if (is_null($this->scopesConfig)) {
            // prepare result
            $this->scopesConfig = include CORE_PATH . '/Treo/Configs/Scopes.php';
        }

        return $this->scopesConfig;
    }
}
