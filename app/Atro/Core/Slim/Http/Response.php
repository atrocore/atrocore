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

namespace Atro\Core\Slim\Http;

class Response extends \Slim\Http\Response
{
    public function getPsrResponse(): \Psr\Http\Message\ResponseInterface
    {
        $psr17Factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        $psr7Response = $psr17Factory->createResponse($this->getStatus());

        foreach ($this->headers->all() as $name => $value) {
            $psr7Response = $psr7Response->withHeader($name, $value);
        }

        $psr7Response = $psr7Response->withBody($psr17Factory->createStream($this->getBody()));

        return $psr7Response;
    }
}
