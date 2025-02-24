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

namespace Atro\Controllers;

use Atro\Core\Exceptions\NotFound;

class UserProfile extends AbstractController
{
    public function actionRead($params, $data, $request)
    {
        $id = $params['id'];
        $entity = $this->getServiceFactory()->create('User')->readEntity($id);

        if (empty($entity)) {
            throw new NotFound();
        }

        return $entity->getValueMap();
    }

}
