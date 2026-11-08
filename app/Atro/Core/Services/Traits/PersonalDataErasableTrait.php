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

namespace Atro\Core\Services\Traits;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;

/**
 * Erasing the personal data of the records according to the erasure mode of each field.
 */
trait PersonalDataErasableTrait
{
    public function massErasePersonalData(array $params): array
    {
        if (!$this->getUser()->isAdmin() && !$this->getUser()->isDataPrivacyAdmin()) {
            throw new Forbidden();
        }

        $params['action']             = 'erasePersonalData';
        $params['maxCountWithoutJob'] = $params['maxCountWithoutJob'] ?? $this->getConfig()->get('massErasePersonalDataMaxCountWithoutJob', 200);
        $params['maxChunkSize']       = $this->getConfig()->get('massErasePersonalDataMaxChunkSize', 3000);
        $params['minChunkSize']       = $this->getConfig()->get('massErasePersonalDataMinChunkSize', 400);
        $params['singleActionMethod'] = 'erasePersonalData';

        $result = $this->executeMassAction($params, function ($id) {
            $this->erasePersonalData($id);
        });

        if (empty($result)) {
            throw new BadRequest("Entity '{$this->getEntityType()}' does not contain personal data.");
        }

        [$count, $errors, $sync, $jobEntity] = array_pad($result, 4, null);

        return $sync
            ? ['count' => $count, 'errors' => $errors, 'sync' => true]
            : ['jobId' => $jobEntity->get('id'), 'sync' => false];
    }

    public function erasePersonalData(string $id): bool
    {
        if (!$this->getUser()->isAdmin() && !$this->getUser()->isDataPrivacyAdmin()) {
            throw new Forbidden();
        }

        $entityType = $this->getEntityType();
        if (empty($this->getMetadata()->get(['scopes', $entityType, 'containsPersonalData']))) {
            throw new BadRequest("Entity '$entityType' does not contain personal data.");
        }

        /** @var \Atro\Core\ORM\Repositories\RDB $repository */
        $repository = $this->getRepository();

        if (empty($entity = $repository->get($id))) {
            throw new NotFound();
        }

        $repository->erasePersonalData($entity);

        return true;
    }
}
