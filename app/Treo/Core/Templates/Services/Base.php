<?php

declare(strict_types=1);

namespace Treo\Core\Templates\Services;

use Espo\ORM\Entity;

/**
 * Class Base
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Base extends \Espo\Core\Templates\Services\Base
{
    /**
     * @var string
     */
    public static $codePattern = '/^[a-z0-9_]*$/';

    /**
     * @inheritdoc
     */
    protected function init()
    {
        // parent init
        parent::init();

        // add dependecies
        $this->addDependency('language');
    }

    /**
     * Is code valid
     *
     * @param Entity $entity
     * @param Entity $entity
     *
     * @return bool
     */
    protected function isCodeValid(Entity $entity, string $key = 'code'): bool
    {
        // prepare result
        $result = false;

        if (!empty($entity->get($key)) && preg_match(self::$codePattern, $entity->get($key))) {
            $result = $this->isUnique($entity, $key);
        }

        return $result;
    }

    /**
     * Entity field is unique?
     *
     * @param Entity $entity
     * @param string $field
     *
     * @return bool
     */
    protected function isUnique(Entity $entity, string $field): bool
    {
        // prepare result
        $result = true;

        // find product
        $fundedEntity = $this->getEntityManager()
            ->getRepository($entity->getEntityName())
            ->select(['id'])
            ->where([$field => $entity->get($field)])
            ->findOne();

        if (!empty($fundedEntity) && $fundedEntity->get('id') != $entity->get('id')) {
            $result = false;
        }

        return $result;
    }

    /**
     * Translate
     *
     * @param string $key
     * @param string $label
     * @param string $scope
     *
     * @return string
     */
    protected function translate(string $key, string $label, $scope = 'Global'): string
    {
        return $this->getInjection('language')->translate($key, $label, $scope);
    }
}
