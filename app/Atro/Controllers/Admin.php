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


namespace Atro\Controllers;

use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Utils\Database\Schema\Schema;

class Admin extends \Espo\Controllers\Admin
{
    public function actionGetSchemaDiff($params, $data, $request): string
    {
        if (!$request->isGet()) {
            throw new NotFound();
        }

        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        $result = '';

        foreach ($this->getSchema()->getDiffQueries() as $query) {
            $result .= $query . PHP_EOL;
        }

        return $result;
    }

    private function getSchema(): Schema
    {
        return $this->getContainer()->get('schema');
    }
}