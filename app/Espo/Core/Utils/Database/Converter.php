<?php

namespace Espo\Core\Utils\Database;

use Espo\Core\Utils\Util;
use Espo\ORM\Entity;

class Converter
{
    private $metadata;

    private $fileManager;

    private $config;

    private $schemaConverter;

    private $schemaFromMetadata = null;

    public function __construct(\Espo\Core\Utils\Metadata $metadata, \Espo\Core\Utils\File\Manager $fileManager, \Espo\Core\Utils\Config $config = null)
    {
        $this->metadata = $metadata;
        $this->fileManager = $fileManager;
        $this->config = $config;
        $this->ormConverter = new Orm\Converter($this->metadata, $this->fileManager, $this->config);
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    protected function getOrmConverter()
    {
        return $this->ormConverter;
    }

    public function process()
    {
        $data = $this->getOrmConverter()->process();

        return $data;
    }
}