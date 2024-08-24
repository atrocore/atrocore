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
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class TranslateOption extends AbstractTwigFunction
{
    const FALLBACK_LANGUAGE = 'en_US';

    protected Language $language;
    public function __construct(Language $language)
    {
        $this->language = $language;
    }
    public function run(string $value, string $languageCode, string $field, string $scope = 'Global'): string
    {
        $initialLanguage = $this->language->getLanguage();
        $this->language->setLanguage($languageCode);
        $translated = $this->language->translateOption($value, $field, $scope);

        if($translated === $value){
            $this->language->setLanguage(self::FALLBACK_LANGUAGE);
            $translated =  $this->language->translateOption($value, $field, $scope);
        }

        if($translated === $value){
            $this->language->setLanguage(self::FALLBACK_LANGUAGE);
            return $this->language->translate($value, 'labels', $scope);
        }

        $this->language->setLanguage($initialLanguage);

        return $translated;
    }
}
