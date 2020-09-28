<?php

namespace Espo\Core\Utils\File;
use Espo\Core\Utils\Util;
use Espo\Core\Utils\Json;

class FileUnifier
{
    private $fileManager;
    private $metadata;

    public function __construct(\Espo\Core\Utils\File\Manager $fileManager, \Espo\Core\Utils\Metadata $metadata = null)
    {
        $this->fileManager = $fileManager;
        $this->metadata = $metadata;
    }

    protected function getFileManager()
    {
        return $this->fileManager;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Unite files content
     *
     * @param array $paths
     * @param bool $isReturnModuleNames - If need to return data with module names
     *
     * @return array
     */
    public function unify(array $paths, $isReturnModuleNames = false)
    {
        $data = $this->loadData($paths['corePath']);

        if (!empty($paths['customPath'])) {
            $data = Util::merge($data, $this->loadData($paths['customPath']));
        }

        return $data;
    }

    /**
     * Load data from a file
     *
     * @param  string $filePath
     * @param  array  $returns
     * @return array
     */
    protected function loadData($filePath, $returns = array())
    {
        if (file_exists($filePath)) {
            $content = $this->getFileManager()->getContents($filePath);
            $data = Json::getArrayData($content);
            if (empty($data)) {
                $GLOBALS['log']->warning('FileUnifier::unify() - Empty file or syntax error - ['.$filePath.']');
                return $returns;
            }

            return $data;
        }

        return $returns;
    }
}
