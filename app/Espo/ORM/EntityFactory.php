<?php

namespace Espo\ORM;

class EntityFactory
{
    protected $metadata;

    protected $entityManager;

    public function __construct(EntityManager $entityManager, Metadata $metadata)
    {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
    }
    public function create($name)
    {
        $className = $this->entityManager->normalizeEntityName($name);
        if (!class_exists($className)) {
            return null;
        }
        $defs = $this->metadata->get($name);
        if (is_null($defs)) {
            return null;
        }
        $entity = new $className($defs, $this->entityManager);
        return $entity;
    }

}

