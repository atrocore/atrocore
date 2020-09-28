<?php

namespace Espo\Core;

abstract class Injectable implements \Espo\Core\Interfaces\Injectable
{
    protected $dependencyList = array();

    protected $injections = array();

    public function inject($name, $object)
    {
        $this->injections[$name] = $object;
    }

    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
    }

    public function __call($methodName, $args)
    {
        if (strpos($methodName, 'get') === 0) {
            $injectionName = lcfirst(substr($methodName, 3));
            if (in_array($injectionName, $this->dependencyList)) {
                return $this->getInjection($injectionName);
            }
        }
        throw new \BadMethodCallException('Method ' . $methodName . ' does not exist');
    }

    protected function getInjection($name)
    {
        return $this->injections[$name];
    }

    protected function addDependency($name)
    {
        if (in_array($name, $this->dependencyList)) return;
        $this->dependencyList[] = $name;
    }

    protected function addDependencyList(array $list)
    {
        foreach ($list as $item) {
            $this->addDependency($item);
        }
    }

    public function getDependencyList()
    {
        return $this->dependencyList;
    }
}
