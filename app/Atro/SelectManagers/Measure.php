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

namespace Atro\SelectManagers;

use Espo\Core\SelectManagers\Base;

class Measure extends Base
{
    protected function boolFilterNotLinkedWithLocale(array &$result)
    {
        if (!empty($localeId = $this->getBoolFilterParameter('notLinkedWithLocale'))) {
            $locale = $this->getEntityManager()->getRepository('Locale')->get($localeId);
            if (!empty($measures = $locale->get('measures')) && count($measures) > 0) {
                $result['whereClause'][] = [
                    'id!=' => array_column($measures->toArray(), 'id')
                ];
            }
        }
    }
}

