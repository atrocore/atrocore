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

namespace Atro\Repositories;

use Atro\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

class PasswordChangeRequest extends Base
{
    public const DEFAULT_RESEND_INTERVAL = 1;
    public const DEFAULT_LIFETIME = 15;

    public function findActive(string $requestId): ?Entity
    {
        if ($requestId === '') {
            return null;
        }

        $lifetime = max(1, (int)$this->getConfig()->get('passwordChangeRequestLifetime', self::DEFAULT_LIFETIME));

        return $this
            ->where([
                'requestId'  => $requestId,
                'createdAt>' => (new \DateTime())->modify("-$lifetime minutes")->format('Y-m-d H:i:s')
            ])
            ->findOne();
    }

    public function removeByUser(string $userId): void
    {
        $this->getDbal()->createQueryBuilder()
            ->delete('password_change_request')
            ->where('user_id = :userId')
            ->setParameter('userId', $userId)
            ->executeStatement();
    }

    public function removeOutdated(): void
    {
        $lifetime = max(1, (int)$this->getConfig()->get('passwordChangeRequestLifetime', self::DEFAULT_LIFETIME));
        $resendInterval = max(1, (int)$this->getConfig()->get('passwordChangeRequestResendInterval', self::DEFAULT_RESEND_INTERVAL));

        $minutes = max($lifetime, $resendInterval);

        $this->getDbal()->createQueryBuilder()
            ->delete('password_change_request')
            ->where('created_at < :createdBefore')
            ->setParameter('createdBefore', (new \DateTime())->modify("-$minutes minutes")->format('Y-m-d H:i:s'))
            ->executeStatement();
    }
}
