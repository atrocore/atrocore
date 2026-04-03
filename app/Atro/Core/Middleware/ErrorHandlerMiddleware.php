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

namespace Atro\Core\Middleware;

use Atro\Core\Http\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            $code = $e->getCode();

            if ($code < 100 || $code >= 600) {
                $code = 500;
            }

            if ($code >= 500) {
                $GLOBALS['log']->error('Uncaught Exception ' . get_class($e) . ': "' . $e->getMessage() . '" at ' . $e->getFile() . ' line ' . $e->getLine(), ['exception' => $e]);
            }

            return new ErrorResponse($code, $e->getMessage());
        }
    }
}
