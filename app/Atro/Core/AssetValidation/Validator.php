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

namespace Atro\Core\AssetValidation;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Injectable;

class Validator extends Injectable
{
    private $instances = [];

    public function __construct()
    {
        $this->addDependency('container');
    }

    public function validate(string $validatorName, $attachment, $params)
    {
        $className = $this->getMetadata()->get(['app', 'config', 'validations', 'classMap', $validatorName]);

        if (!$className || !class_exists($className)) {
            $className = __NAMESPACE__ . "\\Items\\" . ucfirst($validatorName);
        }

        if (!class_exists($className)) {
            throw new BadRequest("Validator with name '{$validatorName}' not found");
        }

        if (!is_a($className, Base::class, true)) {
            throw new Error("Class must implements 'Base' validator");
        }

        if (!isset($this->instances[$className])) {
            $this->instances[$className] = new $className($this->getInjection('container'));
        }

        if (!$this->instances[$className]->setAttachment($attachment)->setParams($params)->validate()) {
            $this->instances[$className]->onValidateFail();
        }
    }

    protected function getMetadata()
    {
        return $this->getInjection('container')->get('metadata');
    }
}