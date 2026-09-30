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

namespace Atro\TwigFunction;

use Atro\Core\Twig\AbstractTwigFunction;
use Atro\Repositories\Translation as TranslationRepository;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class Translate extends AbstractTwigFunction
{
    const FALLBACK_LANGUAGE = 'en_US';

    protected EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function run(string $value, string $languageCode = 'en_US', string $category = 'labels', string $scope = 'Global'): string
    {
        $translation = $this->getTranslationRepository()->getTranslation($scope, $category, $value);

        if ($translation === null && $scope !== 'Global') {
            $translation = $this->getTranslationRepository()->getTranslation('Global', $category, $value);
        }

        if ($translation === null) {
            return $value;
        }

        $translated = $translation->get(TranslationRepository::languageToField($languageCode));

        if (empty($translated) && $languageCode !== self::FALLBACK_LANGUAGE) {
            $translated = $translation->get(TranslationRepository::languageToField(self::FALLBACK_LANGUAGE));
        }

        return !empty($translated) ? $translated : $value;
    }

    protected function getTranslationRepository(): TranslationRepository
    {
        return $this->entityManager->getRepository('Translation');
    }
}
