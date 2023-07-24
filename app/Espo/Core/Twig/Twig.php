<?php
/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

declare(strict_types=1);

namespace Espo\Core\Twig;

use Espo\Core\Container;
use Espo\Core\Injectable;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;

class Twig extends Injectable
{
    public function __construct()
    {
        $this->addDependency('container');
    }

    public function renderTemplate(string $template, array $templateData, string $outputType = 'text')
    {
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(['template' => $template]));
        $templateData['config'] = $this->getConfig()->getData();

        try {
            foreach ($this->getMetadata()->get(['twig', 'filters'], []) as $alias => $className) {
                $filter = $this->getContainer()->get($className);
                if ($filter instanceof AbstractTwigFilter) {
                    $twig->addFilter(new \Twig\TwigFilter($alias, [$filter, 'filter']));
                }
            }

            foreach ($this->getMetadata()->get(['twig', 'functions'], []) as $alias => $className) {
                $twigFunction = $this->getContainer()->get($className);
                if ($twigFunction instanceof AbstractTwigFunction) {
                    $twig->addFunction(new \Twig\TwigFunction($alias, [$twigFunction, 'run']));
                }
            }

            $res = $twig->render('template', $templateData);
        } catch (\Throwable $e) {
            $res = 'Error: ' . $e->getMessage();
        }

        switch ($outputType) {
            case 'int':
                $res = (int)$res;
                break;
            case 'float':
                $res = (float)$res;
                break;
            case 'bool':
                $res = (bool)$res;
                break;
            case 'date':
                try {
                    $res = (new \DateTime($res))->format('Y-m-d');
                } catch (\Throwable $e) {
                    $res = null;
                }
                break;
            case 'datetime':
                try {
                    $res = (new \DateTime($res))->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $res = null;
                }
                break;
        }

        return $res;
    }

    protected function getContainer(): Container
    {
        return $this->getInjection('container');
    }

    protected function getConfig(): Config
    {
        return $this->getContainer()->get('config');
    }

    protected function getMetadata(): Metadata
    {
        return $this->getContainer()->get('metadata');
    }
}
