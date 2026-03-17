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

namespace Atro\Handlers\Dashlet;

use Atro\Core\Exceptions;
use Atro\Core\Http\Response\ErrorResponse;
use Atro\Core\Http\Response\JsonResponse;
use Atro\Core\Routing\Route;
use Atro\Core\Utils\Language;
use Atro\Services\DashletInterface;
use Espo\Core\ServiceFactory;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[Route('/Dashlet/{dashletName}', methods: ['GET'])]
class DashletHandler implements MiddlewareInterface
{
    public function __construct(
        private readonly ServiceFactory $serviceFactory,
        private readonly Language $language,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeResult = $request->getAttribute(RouteResult::class);
        $dashletName = $routeResult ? ($routeResult->getMatchedParams()['dashletName'] ?? null) : null;

        if (empty($dashletName)) {
            return new ErrorResponse(400, 'dashletName is required');
        }

        try {
            return new JsonResponse($this->createDashletService($dashletName)->getDashlet());
        } catch (\Throwable $e) {
            return new ErrorResponse($e->getCode() ?: 500, $e->getMessage());
        }
    }

    private function createDashletService(string $dashletName): DashletInterface
    {
        $serviceName    = ucfirst($dashletName) . 'Dashlet';
        $dashletService = $this->serviceFactory->create($serviceName);

        if (!$dashletService instanceof DashletInterface) {
            throw new Exceptions\Error(sprintf($this->language->translate('notDashletService'), $serviceName));
        }

        return $dashletService;
    }

}