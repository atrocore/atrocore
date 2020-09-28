<?php

declare(strict_types=1);

namespace Treo\Core;

/**
 * Class InjectableFactory
 *
 * @author r.ratsun@gmail.com
 */
class InjectableFactory extends \Espo\Core\InjectableFactory
{
    /**
     * @inheritdoc
     */
    public function createByClassName($className)
    {
        if (strpos($className, 'Espo\\') !== false) {
            $treoClassName = str_replace('Espo\\', 'Treo\\', $className);

            if (class_exists($treoClassName)) {
                $className = $treoClassName;
            }
        }
        return parent::createByClassName($className);
    }
}
