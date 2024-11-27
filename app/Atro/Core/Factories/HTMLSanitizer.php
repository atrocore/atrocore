<?php

namespace Atro\Core\Factories;

use Atro\Core\Container;
use Atro\Core\Factories\FactoryInterface;

class HTMLSanitizer implements FactoryInterface
{

    public function create(Container $container)
    {
        return new \Atro\Core\Utils\HTMLSanitizer($container->get('config'));
    }
}
