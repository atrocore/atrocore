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

namespace Atro\Core\FileStorage\Storages;

use \Espo\Core\Interfaces\Injectable;
use Espo\Entities\Attachment;

abstract class Base implements Injectable
{
    /**
     * @var array
     */
    protected $dependencyList = [];

    /**
     * @var array
     */
    protected $injections = array();

    /**
     * @param $name
     * @param $object
     */
    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    /**
     * Base constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Init method
     */
    protected function init()
    {
    }

    /**
     * @param $name
     *
     * @return mixed
     */
    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    /**
     * @param $name
     */
    protected function addDependency($name)
    {
        $this->dependencyList[] = $name;
    }

    /**
     * @param array $list
     */
    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    /**
     * @return array
     */
    public function getDependencyList()
    {
        return $this->dependencyList;
    }

    /**
     * @param Attachment $attachment
     *
     * @return string
     */
    abstract public function getDownloadUrl(Attachment $attachment): string;

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    abstract public function unlink(Attachment $attachment);

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    abstract public function getContents(Attachment $attachment);

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    abstract public function isFile(Attachment $attachment);

    /**
     * @param Attachment $attachment
     * @param            $contents
     *
     * @return mixed
     */
    abstract public function putContents(Attachment $attachment, $contents);

    /**
     * @param Attachment $attachment
     *
     * @return mixed
     */
    abstract public function getLocalFilePath(Attachment $attachment);

    /**
     * @param Attachment $attachment
     *
     * @return array
     */
    abstract public function getThumbs(Attachment $attachment): array;
}
