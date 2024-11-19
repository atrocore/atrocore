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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Espo\Core\DataManager;
use Atro\Core\Utils\Util;
use Espo\ORM\Entity;

class NotificationTemplate extends Base
{
    public function addUiHandlerForLanguage(string $language): void
    {
        if ($language === $this->getConfig()->get('mainLanguage')) {
            return;
        }
        $preparedLocale = ucfirst(Util::toCamelCase(strtolower($language)));

        foreach (['subject', 'body'] as $field) {
            // prepare multi-lang field
            $mField = $field . $preparedLocale;
            $uiHandlers = [];
            foreach ($this->getEntityManager()->getRepository('UiHandler')->find() as $uiHandler) {
                if ($uiHandler->get('entityType') === 'NotificationTemplate' && !empty($uiHandler->get('fields')) && $uiHandler->get('fields')[0] === $field) {
                    $uiHandlers[] = $uiHandler;
                }
            }
            foreach ($uiHandlers as $uiHandler) {
                /** @var Entity $newUiHandler */
                try {
                    $newUiHandler = clone $uiHandler;
                    $newUiHandler->setIsNew(true);
                    $newUiHandler->setIsSaved(false);
                    $newUiHandler->set('name', str_replace($field, $mField, $uiHandler->get('name')));
                    $newUiHandler->set('id', null);
                    $newUiHandler->set('hash', null);
                    $newUiHandler->set('code', ($uiHandler->get('code') ?? '') . $preparedLocale);
                    $newUiHandler->set('fields', [$mField]);
                    $this->getEntityManager()->saveEntity($newUiHandler);
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
