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

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Atro\Core\ExpressionLanguage\Functions\FunctionInterface;
use Atro\Core\Factories\FactoryInterface as Factory;

class ExpressionLanguage implements Factory
{
    public function create(Container $container)
    {
        $expressionLanguage = new \Symfony\Component\ExpressionLanguage\ExpressionLanguage();

        foreach ($container->get('metadata')->get('app.expressionLanguageFunctions', []) as $name => $data) {
            if (empty($data['handler'])) {
                continue;
            }

            $className = $data['handler'];

            // handlers are resolved on first use, so registering a function costs nothing
            $resolve = static function () use ($container, $className, $name): FunctionInterface {
                $handler = $container->get($className);

                if (!$handler instanceof FunctionInterface) {
                    throw new \LogicException(sprintf('Expression function "%s" must implement ' . FunctionInterface::class . '.', $name));
                }

                return $handler;
            };

            $expressionLanguage->register(
                $name,
                static fn(string ...$arguments): string => $resolve()->compile(...$arguments),
                static fn(array $values, mixed ...$arguments): mixed => $resolve()->evaluate(...$arguments)
            );
        }

        return $expressionLanguage;
    }
}
