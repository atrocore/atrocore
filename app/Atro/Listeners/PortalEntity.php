<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.md, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Listeners;

use Atro\Core\Application as App;
use Atro\Core\EventManager\Event;
use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;

/**
 * Class PortalEntity
 */
class PortalEntity extends AbstractListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // prepare url
        $this->preparePortalUrl($event->getArgument('entity'));

        // validate url
        $this->validateUrl($event->getArgument('entity'));
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function afterSave(Event $event)
    {
        // set url
        $this->setUrl($event->getArgument('entity'));
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        // unsetUrl
        $this->unsetUrl($event->getArgument('entity'));
    }

    /**
     * Prepare portal url
     *
     * @param \Espo\Listeners\Entity $entity
     *
     * @throws BadRequest
     */
    protected function preparePortalUrl(Entity $entity): void
    {
        // get site url
        $siteUrl = $this->getConfig()->get('siteUrl');

        if (empty($siteUrl)) {
            throw new BadRequest($this->translate('siteUrlEmpty', 'exceptions'));
        }

        // get domain
        $domain = str_replace(['http://', 'https://'], ['', ''], $siteUrl);

        // get entity data
        $data = $entity->toArray();

        // prepare url
        if (isset($data['url']) && empty($data['url'])) {
            $entity->set('url', $siteUrl . '/portal-' . $entity->get('id'));
        } else {
            if (!empty($data['url'])
                && preg_match_all("/^(http|https)\:\/\/{$domain}\/(.*)$/", $data['url'], $matches)) {
                $parts = explode('/', $matches[2][0]);
                if (count($parts) > 1) {
                    $path = implode('-', $parts);
                } else {
                    $path = $matches[2][0];
                }

                $entity->set('url', $siteUrl . '/' . $path);
            }
        }
    }

    /**
     * Validate URL
     *
     * @return null
     * @throws BadRequest
     */
    protected function validateUrl(Entity $entity)
    {
        if (empty($url = $entity->get('url'))) {
            return null;
        }

        // validate url
        if (!filter_var($url, FILTER_VALIDATE_URL)
        ) {
            throw new BadRequest($this->translate('urlIsInvalid', 'exceptions'));
        }

        if (preg_match_all('/^(http|https)\:\/\/(.*)\/(.*)$/', $url, $matches)) {
            if (!empty($path = $matches[3][0])) {
                if (!preg_match('/^[a-z0-9\-]*$/', $path)) {
                    throw new BadRequest($this->translate('urlIsInvalid', 'exceptions'));
                }
            }
        }

        // get all urls
        $urls = App::getPortalUrlFileData();

        // validate by unique
        if (in_array($url, $urls)) {
            if (array_search($url, $urls) != $entity->get('id')) {
                throw new BadRequest($this->translate('suchUrlExists', 'exceptions'));
            }
        }

        return null;
    }

    /**
     * Set url
     *
     * @param \Espo\Listeners\Entity $entity
     */
    protected function setUrl(Entity $entity): void
    {
        if (!empty($url = $entity->get('url'))) {
            // get urls
            $urls = App::getPortalUrlFileData();

            // push
            $urls[$entity->get('id')] = $url;

            // save
            App::savePortalUrlFile($urls);
        }
    }

    /**
     * Unset url
     *
     * @param \Espo\Listeners\Entity $entity
     */
    protected function unsetUrl(Entity $entity): void
    {
        // get urls
        $urls = App::getPortalUrlFileData();

        if (isset($urls[$entity->get('id')])) {
            // delete
            unset($urls[$entity->get('id')]);

            // save
            App::savePortalUrlFile($urls);
        }
    }

    /**
     * @param string $label
     * @param string $category
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $label, string $category = 'labels', string $scope = 'Global'): string
    {
        return $this->getContainer()->get('language')->translate($label, $category, $scope);
    }
}
