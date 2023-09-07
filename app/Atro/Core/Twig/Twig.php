<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\Core\Twig;

use Atro\Core\Container;
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
                    $filter->setTemplateData($templateData);
                    $twig->addFilter(new \Twig\TwigFilter($alias, [$filter, 'filter']));
                }
            }

            foreach ($this->getMetadata()->get(['twig', 'functions'], []) as $alias => $className) {
                $twigFunction = $this->getContainer()->get($className);
                if ($twigFunction instanceof AbstractTwigFunction) {
                    $twigFunction->setTemplateData($templateData);
                    $twig->addFunction(new \Twig\TwigFunction($alias, [$twigFunction, 'run']));
                }
            }

            $res = $twig->render('template', $templateData);
        } catch (\Throwable $e) {
            return 'Error: ' . $e->getMessage();
        }

        $res = trim($res);

        if (strtolower($res) === 'null' || ($outputType !== 'text' && $res === '')) {
            return null;
        }

        switch ($outputType) {
            case 'int':
                $res = (int)$res;
                break;
            case 'float':
                $res = (float)$res;
                break;
            case 'bool':
                $res = strtolower($res) === 'true' || $res === '1';
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
