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

declare(strict_types=1);

namespace Espo\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions;
use Espo\Services\DashletInterface;
use Slim\Http\Request;

class Dashlet extends Base
{
    public function actionGetDashlet($params, $data, Request $request): array
    {
        // is get?
        if (!$request->isGet()) {
            throw new Exceptions\BadRequest();
        }

        if (!empty($params['dashletName'])) {
            return $this->createDashletService($params['dashletName'])->getDashlet();
        }

        throw new Exceptions\Error();
    }

    /**
     * Create dashlet service
     *
     * @param string $dashletName
     *
     * @return DashletInterface
     * @throws Exceptions\Error
     */
    protected function createDashletService(string $dashletName): DashletInterface
    {
        $serviceName = ucfirst($dashletName) . 'Dashlet';

        $dashletService = $this->getService($serviceName);

        if (!$dashletService instanceof DashletInterface) {
            $message = sprintf($this->translate('notDashletService'), $serviceName);

            throw new Exceptions\Error($message);
        }

        return $dashletService;
    }

    /**
     * Translate
     *
     * @param string $key
     *
     * @param string $category
     *
     * @return string
     */
    protected function translate(string $key, string $category = 'exceptions'): string
    {
        return $this->getContainer()->get('language')->translate($key, $category);
    }
}
