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

namespace Atro\Migrations;

use Atro\Core\Migration\Base;

class V1Dot10Dot47 extends V1Dot10Dot36
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-07-23 16:00:00');
    }

    public function up(): void
    {
        // Fix for 1.10.36 not executed
        parent::up();

        // Update Templates
        foreach (V1Dot10Dot41::getTemplateData() as $template) {
            try {
                $query = $this->getConnection()->createQueryBuilder()
                    ->update('email_template')
                    ->where('id = :id')
                    ->set('name', ':name')
                    ->set('code', ':code')
                    ->set('subject', ':subject')
                    ->set('body', ':body');

                if (in_array('de_DE', array_column($this->getConfig()->get('locales') ?? [], 'language'))) {
                    $query->set('subject_de_de', ':subject_de_de')
                        ->set('body_de_de', ':body_de_de');
                }

                $query->setParameters($template)
                    ->executeStatement();
            } catch (\Exception $e) {

            }
        }

    }

    public function down(): void
    {

    }
}
