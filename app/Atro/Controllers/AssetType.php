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

use Atro\Core\Templates\Controllers\Base;
use  Atro\Core\Exceptions\Forbidden;

/**
 * Class AssetType
 */
class AssetType extends Base
{
    /**
     * @inheritDoc
     */
    public function actionCreate($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionCreate($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionUpdate($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionUpdate($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionDelete($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionDelete($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionMassUpdate($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionMassUpdate($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionMassDelete($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionMassDelete($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionCreateLink($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionCreateLink($params, $data, $request);
    }

    /**
     * @inheritDoc
     */
    public function actionRemoveLink($params, $data, $request)
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }

        return parent::actionRemoveLink($params, $data, $request);
    }
}
