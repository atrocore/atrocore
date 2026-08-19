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

namespace Atro\Repositories;

use Atro\Core\Exceptions\BadRequest;
use Atro\Core\Templates\Repositories\Base;
use Atro\Core\DataManager;
use Atro\Entities\Action as ActionEntity;
use Espo\ORM\Entity;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

class Action extends Base
{
    public const array EXPRESSION_NAMES = ['entity', 'uiRecord', 'uiRecordFromName', 'uiRecordFrom'];

    protected function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        if (
            $entity->isAttributeChanged('targetEntity')
            && in_array($entity->get('type'), ['update', 'email', 'delete'])
        ) {
            $entity->set('searchEntity', $entity->get('targetEntity'));
        }

        if (
            $entity->isAttributeChanged('searchEntity') && $entity->get('searchEntity')
            && in_array($entity->get('type'), ['create', 'createOrUpdate'])
            && $entity->get('updateType') !== 'script'
        ) {
            $entity->set('updateType', 'script');
        }

        $this->validateExpression($entity);
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();
        $this->saveExpression($entity);

        parent::afterSave($entity, $options);
    }

    protected function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteCacheFile();

        parent::afterRemove($entity, $options);
    }

    public function deleteCacheFile(): void
    {
        if (empty($this->getMemoryStorage()->get('importJobId'))) {
            $file = DataManager::CACHE_DIR_PATH . '/dynamic_action.json';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->getConfig()->remove('cacheTimestamp');
            $this->getConfig()->save();

            DataManager::pushPublicData('dataTimestamp', (new \DateTime())->getTimestamp());
        }
    }

    protected function validateExpression(ActionEntity $action): void
    {
        if ($action->get('conditionsType') === 'expression' && $action->isAttributeChanged('conditionsExpression')) {
            if (empty($action->get('conditionsExpression'))) {
                throw new BadRequest($this->translateException('expressionCannotBeEmpty'));
            }

            try {
                $this->getExpressionLanguage()->lint($action->get('conditionsExpression'), self::EXPRESSION_NAMES);
            } catch (SyntaxError $e) {
                throw new BadRequest($e->getMessage());
            }
        }
    }

    protected function saveExpression(ActionEntity $action): void
    {
        if ($action->get('conditionsType') === 'expression' && $action->isAttributeChanged('conditionsExpression')) {
            echo '<pre>';
            print_r('123');
            die();
        }
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('expressionLanguage');
    }

    protected function getExpressionLanguage(): ExpressionLanguage
    {
        return $this->getInjection('expressionLanguage');
    }
}
