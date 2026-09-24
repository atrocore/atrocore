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

namespace Atro\Core\LanguageResolvers;

use Atro\Core\Container;
use Atro\Core\Utils\Config;
use Atro\Core\Utils\Language;
use Atro\Core\Utils\Metadata;
use Atro\Core\Utils\Util;
use Atro\DTOs\TranslationKeyDTO;

abstract class AbstractLanguageResolver
{
    protected Container $container;
    protected Language $language;

    private ?array $contentLanguages = null;
    private ?string $mainLanguageName = null;

    public function __construct(Container $container, Language $language)
    {
        $this->container = $container;
        $this->language = $language;
    }

    abstract public function supports(TranslationKeyDTO $key): bool;

    abstract public function resolve(TranslationKeyDTO $key): ?string;

    /**
     * @return iterable<TranslationKeyDTO>
     */
    abstract public function getKeys(): iterable;

    public function decoratesStoredTranslation(): bool
    {
        return false;
    }

    protected function getContentLanguages(): array
    {
        if ($this->contentLanguages === null) {
            $this->contentLanguages = [];

            $mainLanguageCode = $this->getConfig()->get('mainLanguage');

            foreach ($this->getConfig()->get('referenceData.Language', []) as $item) {
                if ($item['code'] === $mainLanguageCode) {
                    $this->mainLanguageName = $item['name'];
                    continue;
                }
                $this->contentLanguages[$item['code']] = $item['name'];
            }
        }

        return $this->contentLanguages;
    }

    protected function getMainLanguageName(): ?string
    {
        $this->getContentLanguages();

        return $this->mainLanguageName;
    }

    protected function getLanguageSuffix(string $languageCode): string
    {
        return ucfirst(Util::toCamelCase(strtolower($languageCode)));
    }

    protected function getMetadata(): Metadata
    {
        return $this->container->get('metadata');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }
}
