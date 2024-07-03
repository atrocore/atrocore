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


use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Controllers\Base;

class PreviewTemplate extends Base
{
    public function actionGetHtmlPreview($params, $data, $request)
    {
        if (!$request->isGet()) {
            throw new BadRequest();
        }
        if (empty($request->get('previewTemplateId')) || empty($request->get($data, 'entityId'))) {
            throw new BadRequest();
        }

        return ['htmlPreview' => $this->getRecordService()->getHtmlPreview($request->get('previewTemplateId'), $request->get('entityId'))];
    }

}