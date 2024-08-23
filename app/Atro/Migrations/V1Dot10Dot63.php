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
use Doctrine\DBAL\ParameterType;
use Espo\Core\Utils\Util;

class V1Dot10Dot63 extends Base
{
    public function getMigrationDateTime(): ?\DateTime
    {
        return new \DateTime('2024-08-21 10:00:00');
    }

    public function up(): void
    {
        $rules = array_filter(V1Dot10Dot50::getDefaultRules(), fn($d) => $d['occurrence'] === 'updating' && $d['entity'] === '');

        if(empty($rules)){
            return;
        }

        $rule = array_values($rules)[0];


        foreach (['email','system'] as $transport){
            if(!empty($rule['templates'][$transport]['id']) && !empty($rule['templates'][$transport]['data'])){
                try{
                    $result = $this->getConnection()->createQueryBuilder()
                        ->select('data')
                        ->from('notification_template')
                        ->where('id = :id')
                        ->setParameter('id', $rule['templates'][$transport]['id'])
                        ->fetchAssociative();

                    $oldData = @json_decode($result['data'], true);
                    $newData = $rule['templates'][$transport]['data'];

                    if(!empty($oldData['field']) && !empty($newData['field'])){
                        $newData['field'] = array_merge($oldData['field'], $newData['field']);
                    }

                    $this->getConnection()->createQueryBuilder()
                        ->update('notification_template')
                        ->set('data',':data')
                        ->where('id = :id')
                        ->setParameter('id', $rule['templates'][$transport]['id'])
                        ->setParameter('data', json_encode($newData))
                        ->executeStatement();
                }catch (\Throwable $e){

                }
            }
        }
    }

    public function down(): void
    {
        $this->up();
    }
}
