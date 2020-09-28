<?php

declare(strict_types=1);

namespace Treo\Core\Loaders;

/**
 * Class FormulaManager
 *
 * @author r.ratsun@gmail.com
 */
class FormulaManager extends Base
{
    /**
     * @inheritDoc
     */
    public function load()
    {
        $formulaManager = new \Espo\Core\Formula\Manager(
            $this->getContainer(),
            $this->getContainer()->get('metadata')
        );

        return $formulaManager;
    }
}
