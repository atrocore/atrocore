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

class V2Dot0Dot28 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2025-08-11 18:00:00');
    }

    public function up(): void
    {
        $this->updateActions();
        $this->updateEmailTemplates();
        $this->updatePreviewTemplates();
        $this->updateNotificationTemplates();
    }

    protected function updateActions(): void
    {
        $actions = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'data')
            ->from('action')
            ->where('data IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($actions as $action) {
            if (strpos($action['data'], 'sku') !== false) {
                $action['data'] = str_replace('.sku', '.number', $action['data']);
                $action['data'] = str_replace('"sku"', '"number"', $action['data']);
                $action['data'] = str_replace("'sku'", "'number'", $action['data']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('action')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $action['id'])
                        ->setParameter('data', $action['data'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function updateEmailTemplates(): void
    {
        if (file_exists('data/reference-data/EmailTemplate.json')) {
            $result = @json_decode(file_get_contents('data/reference-data/EmailTemplate.json'), true);

            if (!empty($result) && is_array($result)) {
                foreach ($result as $key => $template) {
                    if (strpos($template['subject'], 'sku') !== false) {
                        $result[$key]['subject'] = str_replace('.sku', '.number', $result[$key]['subject']);
                        $result[$key]['subject'] = str_replace('"sku"', '"number"', $result[$key]['subject']);
                        $result[$key]['subject'] = str_replace("'sku'", "'number'", $result[$key]['subject']);
                    }

                    if (strpos($template['body'], 'sku') !== false) {
                        $result[$key]['body'] = str_replace('.sku', '.number', $result[$key]['body']);
                        $result[$key]['body'] = str_replace('"sku"', '"number"', $result[$key]['body']);
                        $result[$key]['body'] = str_replace("'sku'", "'number'", $result[$key]['body']);
                    }
                }

                file_put_contents('data/reference-data/EmailTemplate.json', json_encode($result));
            }
        }
    }

    protected function updatePreviewTemplates(): void
    {
        $templates = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'template')
            ->from('preview_template')
            ->where('template IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($templates as $template) {
            if (strpos($template['template'], '.sku') !== false) {
                $template['template'] = str_replace('.sku', '.number', $template['template']);
                $template['template'] = str_replace('"sku"', '"number"', $template['template']);
                $template['template'] = str_replace("'sku'", "'number'", $template['template']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('preview_template')
                        ->set('template', ':template')
                        ->where('id = :id')
                        ->setParameter('id', $template['id'])
                        ->setParameter('template', $template['template'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }

    protected function updateNotificationTemplates(): void
    {
        $templates = $this
            ->getConnection()
            ->createQueryBuilder()
            ->select('id', 'data')
            ->from('notification_template')
            ->where('data IS NOT NULL')
            ->andWhere('deleted = :false')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->fetchAllAssociative();

        foreach ($templates as $template) {
            if (strpos($template['data'], '.sku') !== false) {
                $template['data'] = str_replace('.sku', '.number', $template['data']);
                $template['data'] = str_replace('"sku"', '"number"', $template['data']);
                $template['data'] = str_replace("'sku'", "'number'", $template['data']);

                try {
                    $this
                        ->getConnection()
                        ->createQueryBuilder()
                        ->update('notification_template')
                        ->set('data', ':data')
                        ->where('id = :id')
                        ->setParameter('id', $template['id'])
                        ->setParameter('data', $template['data'])
                        ->executeStatement();
                } catch (\Throwable $e) {

                }
            }
        }
    }
}
