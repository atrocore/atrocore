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
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Exceptions\Forbidden;

class File extends Base
{
    public function actionUploadProxy($params, $data, $request)
    {
        if (!$request->isPost() || !property_exists($data, 'url') || empty($data->url)) {
            throw new BadRequest();
        }

        // Open stream to file URL
        $fileStream = fopen($data->url, 'r');
        if (!$fileStream) {
            throw new \Exception('Failed to open file stream');
        }

        // Set content type to octet-stream for downloading large files
        header("Content-Type: application/octet-stream");

        // Stream the file content
        while (!feof($fileStream)) {
            // Read and output in 16MB chunks
            echo fread($fileStream, 1024 * 1024 * 16);
            // Flush output buffer to ensure immediate output
            flush();
        }

        // Close the file stream
        fclose($fileStream);

        exit(0);
    }

    public function actionCreate($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'create')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->createEntity($data);
    }

    public function actionReupload($params, $data, $request)
    {
        if (!$request->isPut() || !property_exists($data, 'reupload') || empty($data->reupload)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->check($this->name, 'edit')) {
            throw new Forbidden();
        }

        return $this->getRecordService()->createEntity($data);
    }

    public function actionMassDownload($params, $data, $request)
    {
        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (!$this->getAcl()->check($this->name, 'read')) {
            throw new Forbidden();
        }

        if (!property_exists($data, 'where')) {
            throw new BadRequest();
        }

        $where = $this->prepareWhereQuery(json_decode(json_encode($data->where), true));

        return $this->getRecordService()->massDownload($where);
    }
}
