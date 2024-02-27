<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
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

namespace Espo\Core\Utils\Api;

use Atro\Core\Exceptions\WithStatusReasonData;

class Output
{
    private $slim;

    protected $errorDesc = array(
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Page Not Found',
        409 => 'Conflict',
        500 => 'Internal Server Error',
    );

    protected $ignorePrintXStatusReasonExceptionClassNameList = [
        'PDOException'
    ];

    public function __construct(\Espo\Core\Utils\Api\Slim $slim)
    {
        $this->slim = $slim;
    }

    protected function getSlim()
    {
        return $this->slim;
    }

    /**
    * Output the result
    *
    * @param mixed $data - JSON
    */
    public function render($data = null)
    {
        if (is_array($data)) {
            $dataArr = array_values($data);
            $data = empty($dataArr[0]) ? false : $dataArr[0];
        }

        ob_clean();
        echo $data;
    }

    public function processError($message = 'Error', $code = 500, $isPrint = false, $exception = null)
    {
        $currentRoute = $this->getSlim()->router()->getCurrentRoute();

        if (isset($currentRoute)) {
            $inputData = $this->getSlim()->request()->getBody();
            $inputData = $this->clearPasswords($inputData);
            $GLOBALS['log']->error('API ['.$this->getSlim()->request()->getMethod().']:'.$currentRoute->getPattern().', Params:'.print_r($currentRoute->getParams(), true).', InputData: '.$inputData.' - '.$message);
        }

        $this->displayError($message, $code, $isPrint, $exception);
    }

    /**
    * Output the error and stop app execution
    *
    * @param string $text
    * @param int $statusCode
    *
    * @return void
    */
    public function displayError($text, $statusCode = 500, $isPrint = false, $exception = null)
    {
        $GLOBALS['log']->error('Display Error: '.$text.', Code: '.$statusCode.' URL: '.$_SERVER['REQUEST_URI']);

        ob_clean();

        if (!empty( $this->slim)) {
            $toPrintXStatusReason = true;
            if ($exception && in_array(get_class($exception), $this->ignorePrintXStatusReasonExceptionClassNameList)) {
                $toPrintXStatusReason = false;
            }
            $this->getSlim()->response()->setStatus($statusCode);
            if ($toPrintXStatusReason) {
                $this->getSlim()->response()->headers->set('X-Status-Reason', $text);
                $this->getSlim()->response()->body($text);
                if (!empty($exception) && $exception instanceof WithStatusReasonData){
                    $this->getSlim()->response()->headers->set('X-Status-Reason-Data', $exception->getStatusReasonData());
                }
            }

            if ($isPrint) {
                $status = $this->getCodeDesc($statusCode);
                $status = isset($status) ? $statusCode.' '.$status : 'HTTP '.$statusCode;
                $this->getSlim()->printError($text, $status);
            }

            $this->getSlim()->stop();
        } else {
            $GLOBALS['log']->info('Could not get Slim instance. It looks like a direct call (bypass API). URL: '.$_SERVER['REQUEST_URI']);
            die($text);
        }
    }

    /**
     * Get status code desription
     *
     * @param  int $statusCode
     * @return string | null
     */
    protected function getCodeDesc($statusCode)
    {
        if (isset($this->errorDesc[$statusCode])) {
            return $this->errorDesc[$statusCode];
        }

        return null;
    }

    /**
     * Clear passwords for inputData
     *
     * @param  string $inputData
     *
     * @return string
     */
    protected function clearPasswords($inputData)
    {
        return preg_replace('/"(.*?password.*?)":".*?"/i', '"$1":"*****"', $inputData);
    }
}
