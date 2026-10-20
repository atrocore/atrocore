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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V2Dot6Dot3 extends Base
{
    private const MODULE_SENSITIVE_FIELDS = [
        'User'       => ['password', 'passwordConfirm', 'currentPassword'],
        'Connection' => [
            'atrocoreToken',
            'password',
            'oauthClientSecret',
            'oauthTokenSecret',
            'smtpPassword',
            'smtpClientSecret',
            'apiKeyValue',
            's3Secret',
            'openAiApiKey',
            'geminiApiKey',
            'jasperApiKey',
            'refreshToken',
            'bynderToken',
            'bynderClientSecret',
            'cloudinaryApiSecret',
            'msClientSecret',
            'azureSasUrl',
            'dmsPassword',
        ],
        'Settings'   => ['authenticationKey', 'ldapPassword', 'etimClientSecret', 'oidcClientSecret'],
    ];

    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2026-10-19 12:00:00');
    }

    public function up(): void
    {
        if ($this->isPgSQL()) {
            $this->exec("UPDATE action_history_record SET data = regexp_replace(data, 'auth-token=[^;\"]*', 'auth-token=***', 'g') WHERE data LIKE '%auth-token=%'");
        } else {
            try {
                $this->getDbal()->executeStatement("UPDATE action_history_record SET data = REGEXP_REPLACE(data, 'auth-token=[^;\"]*', 'auth-token=***') WHERE data LIKE '%auth-token=%'");
            } catch (\Throwable $e) {
                $this->exec("DELETE FROM action_history_record WHERE data LIKE '%auth-token=%'");
            }
        }

        $sensitiveProperties = $this->getSensitiveProperties();

        $limit = 1000;
        $offset = 0;

        while (true) {
            $qb = $this->getDbal()->createQueryBuilder()
                ->select('id', 'data')
                ->from('action_history_record')
                ->orderBy('id')
                ->setFirstResult($offset)
                ->setMaxResults($limit);

            $conditions = [];
            foreach (array_keys($sensitiveProperties) as $i => $property) {
                $conditions[] = $qb->expr()->like('data', ":property$i");
                $qb->setParameter("property$i", '%"' . $property . '"%');
            }
            $qb->andWhere($qb->expr()->or(...$conditions));
            $qb->andWhere($qb->expr()->in('action', [$qb->expr()->literal('POST'), $qb->expr()->literal('PATCH')]));

            $rows = $qb->fetchAllAssociative();
            if (empty($rows)) {
                break;
            }

            $offset += $limit;

            foreach ($rows as $row) {
                $data = json_decode((string)$row['data'], true);
                if (!is_array($data['request']['body'] ?? null)) {
                    continue;
                }

                $body = $this->maskSensitiveProperties($data['request']['body'], $sensitiveProperties);
                if ($body === $data['request']['body']) {
                    continue;
                }
                $data['request']['body'] = $body;

                $this->getDbal()->createQueryBuilder()
                    ->update('action_history_record')
                    ->set('data', ':data')
                    ->where('id = :id')
                    ->setParameter('data', json_encode($data))
                    ->setParameter('id', $row['id'])
                    ->executeStatement();
            }
        }
    }

    protected function exec(string $sql): void
    {
        try {
            $this->getPDO()->exec($sql);
        } catch (\Throwable $e) {
        }
    }

    private function getSensitiveProperties(): array
    {
        $properties = [];

        foreach (self::MODULE_SENSITIVE_FIELDS as $fields) {
            foreach ($fields as $field) {
                $properties[$field] = true;
            }
        }

        foreach (glob('data/metadata/entityDefs/*.json') ?: [] as $file) {
            $entityDefs = json_decode((string)file_get_contents($file), true);
            if (!is_array($entityDefs) || !is_array($entityDefs['fields'] ?? null)) {
                continue;
            }
            foreach ($entityDefs['fields'] as $field => $fieldDefs) {
                if (is_array($fieldDefs) && ($fieldDefs['type'] ?? null) === 'password') {
                    $properties[$field] = true;
                }
            }
        }

        return $properties;
    }

    private function maskSensitiveProperties(array $data, array $properties): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($properties[$key])) {
                if ($value !== null && $value !== '' && $value !== []) {
                    $data[$key] = '***';
                }
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveProperties($value, $properties);
            }
        }

        return $data;
    }
}
