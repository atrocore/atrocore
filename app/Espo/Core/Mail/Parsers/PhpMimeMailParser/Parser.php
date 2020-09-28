<?php

namespace Espo\Core\Mail\Parsers\PhpMimeMailParser;

use \PhpMimeMailParser\Attachment;

class Parser extends \PhpMimeMailParser\Parser
{
    public function getAttachments($include_inline = true)
    {
        $attachments = [];
        $dispositions = ['attachment', 'inline'];
        $non_attachment_types = ['text/plain', 'text/html'];
        $nonameIter = 0;

        foreach ($this->parts as $part) {
            $disposition = $this->getPart('content-disposition', $part);
            $filename = 'noname';

            if (isset($part['disposition-filename'])) {
                $filename = $this->decodeHeader($part['disposition-filename']);
            } elseif (isset($part['content-name'])) {
                // if we have no disposition but we have a content-name, it's a valid attachment.
                // we simulate the presence of an attachment disposition with a disposition filename
                $filename = $this->decodeHeader($part['content-name']);
                if (!$disposition) {
                    $disposition = 'attachment';
                }
            } elseif (!in_array($part['content-type'], $non_attachment_types, true)
                && substr($part['content-type'], 0, 10) !== 'multipart/'
                ) {
                // if we cannot get it by getMessageBody(), we assume it is an attachment
                if ($disposition !== 'inline') {
                    $disposition = 'attachment';
                }
            }

            if (in_array($disposition, $dispositions) === true && isset($filename) === true) {
                if ($filename == 'noname') {
                    $nonameIter++;
                    $filename = 'noname'.$nonameIter;
                }

                $headersAttachments = $this->getPart('headers', $part);
                $contentidAttachments = $this->getPart('content-id', $part);

                $mimePartStr = $this->getPartComplete($part);

                $attachments[] = new Attachment(
                    $filename,
                    $this->getPart('content-type', $part),
                    $this->getAttachmentStream($part),
                    $disposition,
                    $contentidAttachments,
                    $headersAttachments,
                    $mimePartStr
                );
            }
        }

        return $attachments;
    }
}

