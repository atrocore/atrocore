<?php

namespace Espo\Core\Repositories;

use \Espo\ORM\Entity;

class CategoryTree extends \Espo\Core\ORM\Repositories\RDB
{
	protected function afterSave(Entity $entity, array $options = array())
	{
		parent::afterSave($entity, $options);

		$pdo = $this->getEntityManager()->getPDO();
		$query = $this->getEntityManager()->getQuery();

		$parentId = $entity->get('parentId');
		$pathsTableName = $query->toDb($query->sanitize($entity->getEntityType()) . 'Path');

		if ($entity->isNew()) {
			if ($parentId) {
				$sql = "
					INSERT INTO `".$pathsTableName."` (ascendor_id, descendor_id)
						SELECT ascendor_id, ".$pdo->quote($entity->id)."
						FROM `".$pathsTableName."`
						WHERE descendor_id = ".$pdo->quote($parentId)."
						UNION ALL
						SELECT ".$pdo->quote($entity->id).", ".$pdo->quote($entity->id)."
				";
			} else {
				$sql = "
					INSERT INTO `".$pathsTableName."` (ascendor_id, descendor_id)
					VALUES
					(".$pdo->quote($entity->id).", ".$pdo->quote($entity->id).")
				";
			}
			$pdo->query($sql);
		} else {
			if ($entity->isAttributeChanged('parentId')) {
				$sql = "
					DELETE a FROM `".$pathsTableName."` AS a
					JOIN `".$pathsTableName."` AS d ON a.descendor_id = d.descendor_id
					LEFT JOIN `".$pathsTableName."` AS x ON x.ascendor_id = d.ascendor_id AND x.descendor_id = a.ascendor_id
					WHERE d.ascendor_id = ".$pdo->quote($entity->id)." AND x.ascendor_id IS NULL
				";
				$pdo->query($sql);
				if (!empty($parentId)) {
					$sql = "
						INSERT INTO `".$pathsTableName."` (ascendor_id, descendor_id)
							SELECT supertree.ascendor_id, subtree.descendor_id
							FROM `".$pathsTableName."` AS supertree
							JOIN `".$pathsTableName."` AS subtree
							WHERE
								subtree.ascendor_id = ".$pdo->quote($entity->id)." AND
								supertree.descendor_id = ".$pdo->quote($parentId)."
					";
					$pdo->query($sql);
				}
			}
		}
	}

	protected function afterRemove(Entity $entity, array $options = array())
	{
		parent::afterRemove($entity, $options);

		$pdo = $this->getEntityManager()->getPDO();
		$query = $this->getEntityManager()->getQuery();

		$pathsTableName = $query->toDb($query->sanitize($entity->getEntityType()) . 'Path');

		$sql = "DELETE FROM `".$pathsTableName."` WHERE descendor_id = ".$pdo->quote($entity->id)."";
		$pdo->query($sql);
	}
}

