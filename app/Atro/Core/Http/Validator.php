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

namespace Atro\Core\Http;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\OpenApiGenerator;
use Atro\Core\Routing\Route as RouteAttribute;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Validator
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function validateRequest(array $routeConfig, ServerRequestInterface $request): void
    {
        if (!empty($validatorBuilder = $this->getValidatorBuilderForRoute($routeConfig))) {
            try {
                $validatorBuilder->getRequestValidator()->validate($request);
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    public function validateResponse(array $routeConfig, ResponseInterface $response): void
    {
        if (!empty($validatorBuilder = $this->getValidatorBuilderForRoute($routeConfig))) {
            try {
                $operation = new OperationAddress(preg_replace('/:(\w+)/', '{$1}', $routeConfig['route']), $routeConfig['method']);
                $validatorBuilder->getResponseValidator()->validate($operation, $response);
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    public function validateHandlerRequest(RouteAttribute $routeAttr, ServerRequestInterface $request): void
    {
        $builder = $this->getValidatorBuilderForHandler($routeAttr);

        try {
            $builder->getRequestValidator()->validate($request);
        } catch (\Throwable $e) {
            throw new BadRequest($e->getMessage());
        }
    }

    public function validateHandlerResponse(RouteAttribute $routeAttr, string $method, ResponseInterface $response): void
    {
        $builder   = $this->getValidatorBuilderForHandler($routeAttr);
        $operation = new OperationAddress($routeAttr->path, strtolower($method));

        try {
            $builder->getResponseValidator()->validate($operation, $response);
        } catch (\Throwable $e) {
            throw new BadRequest($e->getMessage());
        }
    }

    private function getValidatorBuilderForRoute(array $routeConfig): ?ValidatorBuilder
    {
        if (empty($routeConfig['description'])) {
            return null;
        }

        $schema = $this->container->get(OpenApiGenerator::class)->getSchemaForRoute($routeConfig);

        return (new ValidatorBuilder())->fromJson(json_encode($schema));
    }

    private function getValidatorBuilderForHandler(RouteAttribute $routeAttr): ValidatorBuilder
    {
        $schema = $this->container->get(OpenApiGenerator::class)->getSchemaForHandler($routeAttr);

        return (new ValidatorBuilder())->fromJson(json_encode($schema));
    }
}
