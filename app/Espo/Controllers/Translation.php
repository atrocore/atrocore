<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

namespace Espo\Controllers;

use Atro\Console\AbstractConsole;
use Atro\Console\RefreshTranslations;
use  Atro\Core\Exceptions\BadRequest;
use  Atro\Core\Exceptions\Forbidden;
use  Atro\Core\Exceptions\NotFound;
use Espo\Core\Templates\Controllers\Base;
use Espo\Core\Utils\Language;

/**
 * Class Translation
 */
class Translation extends Base
{
    /**
     * @param mixed $params
     * @param mixed $data
     * @param mixed $request
     *
     * @return array
     * @throws BadRequest
     * @throws NotFound
     */
    public function getActionGetDefaults($params, $data, $request): array
    {
        if (empty($request->get('key'))) {
            throw new BadRequest();
        }

        $records = RefreshTranslations::getSimplifiedTranslates((new Language($this->getContainer()))->getModulesData());

        if (empty($records[$request->get('key')])) {
            throw new NotFound();
        }

        return $records[$request->get('key')];
    }

    public function postActionReset(): bool
    {
        exec(AbstractConsole::getPhpBinPath($this->getConfig()) . " index.php refresh translations >/dev/null");

        return true;
    }

    public function postActionPush(): bool
    {
        return $this->getRecordService()->push();
    }

    /**
     * @inheritDoc
     */
    public function actionCreateLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    /**
     * @inheritDoc
     */
    public function actionMassDelete($params, $data, $request)
    {
        throw new Forbidden();
    }

    /**
     * @inheritDoc
     */
    public function actionRemoveLink($params, $data, $request)
    {
        throw new Forbidden();
    }

    /**
     * @inheritDoc
     */
    protected function checkControllerAccess()
    {
        if (!$this->getUser()->isAdmin()) {
            throw new Forbidden();
        }
    }
}
