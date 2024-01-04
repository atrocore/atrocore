<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Templates\Services\Base;
use Atro\ActionTypes\TypeInterface;

class Action extends Base
{
    protected $mandatorySelectAttributeList = ['data'];

    public function executeNow(string $id, \stdClass $input): bool
    {
        $action = $this->getRepository()->get($id);
        if (empty($action)) {
            throw new NotFound();
        }

        return $this->getActionType($action->get('type'))->executeNow($action, $input);
    }

    protected function getActionType(string $type): TypeInterface
    {
        return $this->getInjection('container')->get("\\Atro\\ActionTypes\\" . ucfirst($type));
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }
}
