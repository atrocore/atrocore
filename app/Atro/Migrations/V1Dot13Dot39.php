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
use Atro\Core\Templates\Repositories\ReferenceData;
use Atro\ORM\DB\RDB\Mapper;
use Doctrine\DBAL\ParameterType;

class V1Dot13Dot39 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-03-20 16:00:00');
    }

    public function up(): void
    {
        $this->exec("ALTER TABLE layout ADD related_link VARCHAR(255) DEFAULT NULL;");
        $this->exec("ALTER TABLE user_entity_layout ADD related_link VARCHAR(255) DEFAULT NULL;");

        $layouts = $this->getConnection()->createQueryBuilder()
            ->from('layout')
            ->select('id', 'entity', 'related_entity')
            ->where('related_link is null and related_entity is not null')
            ->fetchAllAssociative();

        foreach ($layouts as $layout) {
            $relatedLink = $this->getLink($layout['entity'], $layout['related_entity']);
            if (!empty($relatedLink)) {
                $this->getConnection()->createQueryBuilder()
                    ->update('layout')
                    ->set('related_link', ':relatedLink')
                    ->where('id =:id')
                    ->setParameter('relatedLink', $relatedLink)
                    ->setParameter('id', $layout['id'])
                    ->executeStatement();
            }
        }

        $items = $this->getConnection()->createQueryBuilder()
            ->from('user_entity_layout')
            ->select('id', 'entity', 'related_entity')
            ->where('related_link is null and related_entity is not null')
            ->fetchAllAssociative();

        foreach ($items as $item) {
            $relatedLink = $this->getLink($item['entity'], $item['related_entity']);
            if (!empty($relatedLink)) {
                $this->getConnection()->createQueryBuilder()
                    ->update('user_entity_layout')
                    ->set('related_link', ':relatedLink')
                    ->where('id =:id')
                    ->setParameter('relatedLink', $relatedLink)
                    ->setParameter('id', $item['id'])
                    ->executeStatement();
            }
        }

        $this->exec("DROP INDEX IDX_LAYOUT_LAYOUT_PROFILE;");
        $this->exec("CREATE UNIQUE INDEX IDX_LAYOUT_LAYOUT_PROFILE ON layout (layout_profile_id, entity, related_entity, related_link, view_type, deleted);");
       $this->exec("DROP INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE;");
       $this->exec("CREATE UNIQUE INDEX IDX_USER_ENTITY_LAYOUT_UNIQUE ON user_entity_layout (user_id, entity, view_type, related_entity, related_link, layout_profile_id, deleted);");
    }

    public function getLink(string $entity, string $relatedEntity): ?string
    {
        try {
            if (empty($this->metadata)) {
                $this->metadata = (new \Atro\Core\Application())->getContainer()->get('metadata');
            }
            /** @var \Atro\Core\Utils\Metadata $metadata */
            $metadata = $this->metadata;

            foreach ($metadata->get(['entityDefs', $relatedEntity, 'links']) ?? [] as $link => $linkData) {
                if (!empty($linkData['entity']) && $linkData['entity'] === $entity) {
                    return $link;
                }
            }

        } catch (\Throwable $e) {

        }
        return null;
    }

    protected function exec(string $query): void
    {
        try {
            $this->getPDO()->exec($query);
        } catch (\Throwable $e) {
        }
    }
}
