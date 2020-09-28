<?php

namespace Espo\Core\Mail;

use \Espo\Entities\Email;

class MessageWrapper
{
    private $storage;

    private $id;

    private $rawHeader = null;

    private $rawContent = null;

    private $zendMessage = null;

    protected $zendMessageClass = '\Zend\Mail\Storage\Message';

    protected $fullRawContent = null;

    protected $flagList = null;

    public function __construct($storage = null, $id = null, $parser = null)
    {
        if ($storage) {
            $data = $storage->getHeaderAndFlags($id);
            $this->rawHeader = $data['header'];
            $this->flagList = $data['flags'];
        }

        $this->id = $id;
        $this->storage = $storage;
        $this->parser = $parser;
    }

    public function setFullRawContent($content)
    {
        $this->fullRawContent = $content;
    }

    public function getRawHeader()
    {
        return $this->rawHeader;
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function checkAttribute($attribute)
    {
        return $this->getParser()->checkMessageAttribute($this, $attribute);
    }

    public function getAttribute($attribute)
    {
        return $this->getParser()->getMessageAttribute($this, $attribute);
    }

    public function getRawContent()
    {
        if (is_null($this->rawContent)) {
            $this->rawContent = $this->storage->getRawContent($this->id);
        }

        return $this->rawContent;
    }

    public function getFullRawContent()
    {
        if ($this->fullRawContent) {
            return $this->fullRawContent;
        }

        return $this->getRawHeader() . "\n" . $this->getRawContent();
    }

    public function getZendMessage()
    {
        if (!$this->zendMessage) {
            $data = array();
            if ($this->storage) {
                $data['handler'] = $this->storage;
            }
            if ($this->flagList) {
                $data['flags'] = $this->flagList;
            }
            if ($this->fullRawContent) {
                $data['raw'] = $this->fullRawContent;
            } else {
                if ($this->rawHeader) {
                    $data['headers'] = $this->rawHeader;
                }
            }
            if ($this->id) {
                $data['id'] = $this->id;
            }

            $this->zendMessage = new $this->zendMessageClass($data);
        }

        return $this->zendMessage;
    }

    public function getFlags()
    {
        return $this->flagList;
    }

    public function isFetched()
    {
        return !!$this->rawHeader;
    }
}
