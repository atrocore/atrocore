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

use Atro\Console\AbstractConsole;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;
use Atro\Core\Exceptions\NotFound;
use Atro\Core\Templates\Controllers\ReferenceData;
use Atro\Core\Utils\Language;
use Atro\Repositories\Translation as TranslationRepository;

class Translation extends ReferenceData
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

        $records = TranslationRepository::getSimplifiedTranslates((new Language($this->getContainer()))->getModulesData());

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
