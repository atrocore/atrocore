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

use Espo\Core\Controllers\Base;
use Atro\Core\Exceptions;
use Espo\Core\Utils\Language;
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
            /** @var Language $language */
            $language = $this->getContainer()->get('language');

            $message = sprintf($language->translate('notDashletService'), $serviceName);

            throw new Exceptions\Error($message);
        }

        return $dashletService;
    }
}
