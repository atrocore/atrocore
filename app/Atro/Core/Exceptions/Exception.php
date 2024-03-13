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

namespace Atro\Core\Exceptions;

class Exception extends \Exception
{
    protected array $data = [];

    /**
     * @inheritDoc
     */
    public function __construct($message = "", $code = 0, \Throwable $previous = null)
    {
        // decode message to utf8
        $message = mb_convert_encoding($message, 'utf-8', mb_detect_encoding($message));

        parent::__construct($message, $code, $previous);
    }

    public function setDataItem(string $key, $value): Exception
    {
        $this->data[$key] = $value;

        return $this;
    }

    public function getDataItem(string $key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : null;
    }
}
