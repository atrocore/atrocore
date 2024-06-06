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

namespace Atro\Services;

use Atro\ORM\DB\RDB\Mapper;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Services\Base;

class Translation extends Base
{
    public function push(): bool
    {
        $connection = $this->getEntityManager()->getConnection();
        $data = [];
        $data['data'] = $connection->createQueryBuilder()
            ->select('*')
            ->from($connection->quoteIdentifier('translation'))
            ->where('deleted = :true OR is_customized = :true')
            ->setParameter('true', true, Mapper::getParameterType(true))
            ->fetchAllAssociative();

        if (empty($data['data'])) {
            throw new BadRequest($this->getInjection('language')->translate('nothingToPush', 'messages', 'Translation'));
        }

        $data['appId'] = $this->getConfig()->get('appId');
        $data['siteUrl'] = $this->getConfig()->get('siteUrl');
        $data['smtpUsername'] = $this->getConfig()->get('smtpUsername');
        $data['emailFrom'] = $this->getConfig()->get('outboundEmailFromAddress');

        $ch = curl_init('https://my.atrocore.com/api/v1/PushedTranslation');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('language');
    }
}
