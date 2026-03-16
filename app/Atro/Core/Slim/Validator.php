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

namespace Atro\Core\Slim;

use Atro\Core\Container;
use Atro\Core\Exceptions\BadRequest;
use Atro\Core\OpenApiGenerator;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Validator
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function validateRequest(array $routeConfig, ServerRequestInterface $request): void
    {
        if (!empty($validatorBuilder = $this->getValidatorBuilder($routeConfig))) {
            try {
                $validatorBuilder->getRequestValidator()->validate($request);
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    public function validateResponse(array $routeConfig, ResponseInterface $response): void
    {
        if (!empty($validatorBuilder = $this->getValidatorBuilder($routeConfig))) {
            try {
                $operation = new OperationAddress(preg_replace('/:(\w+)/', '{$1}', $routeConfig['route']), $routeConfig['method']);
                $validatorBuilder->getResponseValidator()->validate($operation, $response);
            } catch (\Throwable $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function getValidatorBuilder(array $routeConfig): ?ValidatorBuilder
    {
        if (empty($routeConfig['description'])) {
            return null;
        }

        $schema = $this->container->get(OpenApiGenerator::class)->getSchemaForRoute($routeConfig);

        return (new ValidatorBuilder())->fromJson(json_encode($schema));
    }

}