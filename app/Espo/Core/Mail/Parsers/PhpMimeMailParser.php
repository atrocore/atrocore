<?php

namespace Espo\Core\Mail\Parsers;

class PhpMimeMailParser
{
    private $entityManager;

    private $parserHash = array();

    public function __construct($entityManager)
    {
        $this->entityManager = $entityManager;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getParser($message)
    {
        $key = spl_object_hash($message);
        if (!array_key_exists($key, $this->parserHash)) {
            $this->parserHash[$key] = new PhpMimeMailParser\Parser();
            $raw = $message->getRawHeader();
            if (!$raw) {
                $raw = $message->getFullRawContent();
            }
            $this->parserHash[$key]->setText($raw);
        }

        return $this->parserHash[$key];
    }

    protected function loadContent($message)
    {
        $this->getParser($message);

        $raw = $message->getFullRawContent();
        $this->getParser($message)->setText($raw);
    }

    public function checkMessageAttribute($message, $attribute)
    {
        return $this->getParser($message)->getHeader($attribute) !== false;
    }

    public function getMessageAttribute($message, $attribute)
    {
        if (!$this->checkMessageAttribute($message, $attribute)) return null;

        return $this->getParser($message)->getHeader($attribute);
    }

    public function getMessageMessageId($message)
    {
        return $this->getMessageAttribute($message, 'Message-ID');
    }

    public function getAddressNameMap($message)
    {
        $map = (object) [];

        foreach (['from', 'to', 'cc', 'reply-To'] as $type) {
            if ($this->checkMessageAttribute($message, $type)) {
                $list = $this->getParser($message)->getAddresses($type);
                foreach ($list as $item) {
                    $name = $list[0]['display'];
                    $address = $list[0]['address'];
                    if ($name && $address && $name !== $address) {
                        $map->$address = $name;
                    }
                }

            }
        }

        return $map;
    }

    public function getAddressDataFromMessage($message, $type)
    {
        $addressList = [];
        if ($this->checkMessageAttribute($message, $type)) {
            $list = $this->getParser($message)->getAddresses($type);
            if (count($list)) {
                return [
                    'address' => $list[0]['address'],
                    'name' => $list[0]['display'],
                ];
            }
        }
        return null;
    }

    public function getAddressListFromMessage($message, $type)
    {
        $addressList = [];
        if ($this->checkMessageAttribute($message, $type)) {
            $list = $this->getParser($message)->getAddresses($type);
            foreach ($list as $address) {
                $addressList[] = $address['address'];
            }
        }
        return $addressList;
    }

    public function fetchContentParts(\Espo\Entities\Email $email, $message, &$inlineAttachmentList = [])
    {
        $this->loadContent($message);

        $bodyPlain = '';
        $bodyHtml = '';

        $inlinePartTextList = $this->getParser($message)->getInlineParts('text');
        $inlinePartHtmlList = $this->getParser($message)->getInlineParts('html');
        if (empty($inlinePartTextList)) {
            $bodyPlain = $this->getParser($message)->getMessageBody('text');
        }
        if (empty($inlinePartHtmlList)) {
            $bodyHtml = $this->getParser($message)->getMessageBody('html');
        }

        foreach ($inlinePartTextList as $i => $inlinePart) {
            if ($i) $bodyPlain .= "\n";
            $bodyPlain .= $inlinePart;
        }
        foreach ($inlinePartHtmlList as $i => $inlinePart) {
            if ($i) $bodyHtml .= "<br>";
            $bodyHtml .= $inlinePart;
        }

        if ($bodyHtml) {
            $email->set('isHtml', true);
            $email->set('body', $bodyHtml);
            $email->set('bodyPlain', $bodyPlain);
        } else {
            $email->set('isHtml', false);
            $email->set('body', $bodyPlain);
            $email->set('bodyPlain', $bodyPlain);
        }

        if (!$email->get('body') && $email->hasBodyPlain()) {
            $email->set('body', $email->get('bodyPlain'));
        }

        $attachmentObjList = $this->getParser($message)->getAttachments();
        $inlineIds = array();

        foreach ($attachmentObjList as $attachmentObj) {
            $attachment = $this->getEntityManager()->getEntity('Attachment');

            $content = $attachmentObj->getContent();
            $disposition = $attachmentObj->getContentDisposition();

            $attachment = $this->getEntityManager()->getEntity('Attachment');
            $attachment->set('name', $attachmentObj->getFileName());
            $attachment->set('type', $attachmentObj->getContentType());

            $contentId = $attachmentObj->getContentID();

            if ($disposition == 'inline') {
                $attachment->set('role', 'Inline Attachment');
            } else {
                $attachment->set('role', 'Attachment');
            }

            $attachment->set('contents', $content);

            $this->getEntityManager()->saveEntity($attachment);

            if ($disposition == 'attachment') {
                $email->addLinkMultipleId('attachments', $attachment->id);
                if ($contentId) {
                    $inlineIds[$contentId] = $attachment->id;
                }
            } else if ($disposition == 'inline') {
                if ($contentId) {
                    $inlineIds[$contentId] = $attachment->id;
                    $inlineAttachmentList[] = $attachment;
                } else {
                    $email->addLinkMultipleId('attachments', $attachment->id);
                }
            }
        }

        $body = $email->get('body');

        if (!empty($body)) {
            foreach ($inlineIds as $cid => $attachmentId) {
                if (strpos($body, 'cid:' . $cid) !== false) {
                    $body = str_replace('cid:' . $cid, '?entryPoint=attachment&amp;id=' . $attachmentId, $body);
                } else {
                    $email->addLinkMultipleId('attachments', $attachmentId);
                }
            }
            $email->set('body', $body);
        }
    }
}

