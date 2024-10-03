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

namespace Atro\Core\Utils;

use Atro\Core\Container;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class Language extends \Espo\Core\Utils\Language
{
    public function __construct(Container $container)
    {
        $currentLanguage = self::detectLanguage($container->get('config'), $container->get('preferences'));

        parent::__construct($container, $currentLanguage);
    }

    public function clearCache(): void
    {
        $this->reload();
        foreach ($this->getMetadata()->get('multilang.languageList', []) as $language) {
            $cacheFile = "data/cache/{$language}.json";
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
            }
        }
    }

    protected function getData()
    {
        $currentLanguage = $this->getLanguage();
        if (empty($this->data)) {
            $this->init();
        }

        if ($currentLanguage === self::DEFAULT_LANGUAGE) {
            return $this->data[$currentLanguage];
        }

        if (empty($data = $this->getDataManager()->getCacheData($currentLanguage))) {
            $data = $this->data[self::DEFAULT_LANGUAGE];

//            /** @var Connection $conn */
//            $conn = $this->container->get('connection');
//
//            /** @var ?string $fallbackLanguage */
//            $fallbackLanguage = $conn->createQueryBuilder()
//                ->select('fallback_language')
//                ->from('language')
//                ->where('code=:code')
//                ->andWhere('deleted=:false')
//                ->setParameter('code', $currentLanguage)
//                ->setParameter('false', false, ParameterType::BOOLEAN)
//                ->fetchOne();

            if (!empty($fallbackLanguage)) {
                $data = Util::merge($data, $this->data[$fallbackLanguage] ?? []);
            }

            $data = Util::merge($data, $this->data[$currentLanguage] ?? []);

            $this->getDataManager()->setCacheData($currentLanguage, $data);
        }

        return $data;
    }
}
