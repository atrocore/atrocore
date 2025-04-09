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

namespace Atro\Core\AttributeFieldTypes;

use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Entities\User;
use Espo\ORM\EntityManager;

abstract class AbstractFieldType implements AttributeFieldTypeInterface
{
    protected Config $config;
    protected User $user;
    protected EntityManager $em;
    protected Language $language;

    public function __construct(Container $container)
    {
        $this->config = $container->get('config');
        $this->user = $container->get('user');
        $this->em = $container->get('entityManager');
        $this->language = $container->get('language');
    }

    protected function prepareKey(string $nameKey, array $row): string
    {
        if (!empty($localeId = $this->user->get('localeId'))) {
            $currentLocale = $this->em->getEntity('Locale', $localeId);
            if (!empty($currentLocale)) {
                $languageNameKey = $nameKey . '_' . strtolower($currentLocale->get('languageCode'));
                if (!empty($row[$languageNameKey])) {
                    $nameKey = $languageNameKey;
                }
            }
        }

        return $nameKey;
    }
}
