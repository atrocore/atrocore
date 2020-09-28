<?php

namespace Espo\Core\Mail\Mail\Storage;

class Imap extends \Zend\Mail\Storage\Imap
{
    public function getIdsFromUID($uid)
    {
        $uid = intval($uid) + 1;
        return $this->protocol->search(array('UID ' . $uid . ':*'));
    }

    public function getIdsFromDate($date)
    {
        return $this->protocol->search(array('SINCE "' . $date . '"'));
    }

    public function getHeaderAndFlags($id, $part = null)
    {
        $data = $this->protocol->fetch(['FLAGS', 'RFC822.HEADER'], $id);

        $header = $data['RFC822.HEADER'];

        $flags = [];
        foreach ($data['FLAGS'] as $flag) {
            $flags[] = isset(static::$knownFlags[$flag]) ? static::$knownFlags[$flag] : $flag;
        }

        return array(
            'flags' => $flags,
            'header' => $header
        );
    }

}

