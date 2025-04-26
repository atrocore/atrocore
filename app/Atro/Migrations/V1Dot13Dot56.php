<?php
/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Migrations;

use Atro\Core\Migration\Base;
use Doctrine\DBAL\ParameterType;

class V1Dot13Dot56 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-04-28 10:00:00');
    }

    public function up(): void
    {
        $currentLanguages = $this->getConfig()->get('referenceData.Language', []);
        $mainLanguage = array_filter(array_values($currentLanguages), function (array $language) {
            return $language['role'] === 'main';
        });
        $mainLanguage = !empty($mainLanguage) ? array_shift($mainLanguage) : null;

        $languages = $this->getConnection()
            ->createQueryBuilder()
            ->from('account')
            ->select('id', 'language')
            ->where('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();
        $languages = array_column($languages, 'language', 'id');

        $this->setAccountsLanguageId($languages, $mainLanguage['id'] ?? null);
    }

    protected function setAccountsLanguageId(array $languages, ?string $mainLanguageId): void
    {
        $langs = $this->getLanguagesList();

        foreach ($languages as $id => $language) {
            $langId = $langs[$language] ?? $mainLanguageId;

            $this
                ->getConnection()
                ->createQueryBuilder()
                ->update('account')
                ->set('language', ':language_id')
                ->where('id = :id')
                ->andWhere('deleted = :false')
                ->setParameter('language_id', $langId)
                ->setParameter('id', $id)
                ->setParameter('false', false, ParameterType::BOOLEAN)
                ->executeQuery();
        }
    }

    protected function getLanguagesList(): array
    {
        $result = [];

        $languages = $this->getConfig()->get('referenceData.Language', []);

        foreach ($languages as $code => $language) {
            $result[$code] = $language['id'];
        }

        return $result;
    }
}
