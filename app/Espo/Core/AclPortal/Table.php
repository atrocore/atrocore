<?php

namespace Espo\Core\AclPortal;

use \Espo\Core\Exceptions\Error;

use \Espo\ORM\Entity;
use \Espo\Entities\User;
use \Espo\Entities\Portal;

use \Espo\Core\Utils\Config;
use \Espo\Core\Utils\Metadata;
use \Espo\Core\Utils\FieldManagerUtil;
use \Espo\Core\Utils\File\Manager as FileManager;

class Table extends \Espo\Core\Acl\Table
{
    protected $type = 'aclPortal';

    protected $portal;

    protected $defaultAclType = 'recordAllOwnNo';

    protected $levelList = ['yes', 'all', 'account', 'contact', 'own', 'no'];

    protected $isStrictModeForced = true;

    public function __construct(User $user, Portal $portal, Config $config = null, FileManager $fileManager = null, Metadata $metadata = null, FieldManagerUtil $fieldManager = null)
    {
        if (empty($portal)) {
            throw new Error("No portal was passed to AclPortal\\Table constructor.");
        }
        $this->portal = $portal;
        parent::__construct($user, $config, $fileManager, $metadata, $fieldManager);
    }

    protected function getPortal()
    {
        return $this->portal;
    }

    /**
     * @inheritDoc
     */
    protected function initCacheFilePath()
    {
        // prepare portal cache dir
        $dir = 'data/cache/acl/portal/' . $this->getPortal()->id;

        // create cache dir
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->cacheFilePath = $dir . '/' . $this->getUser()->id . '.json';
    }

    protected function getRoleList()
    {
        $roleList = [];

        $userRoleList = $this->getUser()->get('portalRoles');
        if (!(is_array($userRoleList) || $userRoleList instanceof \Traversable)) {
            throw new Error();
        }
        foreach ($userRoleList as $role) {
            $roleList[] = $role;
        }

        $portalRoleList = $this->getPortal()->get('portalRoles');
        if (!(is_array($portalRoleList) || $portalRoleList instanceof \Traversable)) {
            throw new Error();
        }
        foreach ($portalRoleList as $role) {
            $roleList[] = $role;
        }

        return $roleList;
    }

    protected function getScopeWithAclList()
    {
        $scopeList = [];
        $scopes = $this->getMetadata()->get('scopes');
        foreach ($scopes as $scope => $d) {
            if (empty($d['acl'])) continue;
            if (empty($d['aclPortal'])) continue;
            $scopeList[] = $scope;
        }
        return $scopeList;
    }

    protected function applyDefault(&$table, &$fieldTable)
    {
        parent::applyDefault($table, $fieldTable);

        foreach ($this->getScopeList() as $scope) {
            if (!isset($table->$scope)) {
                $table->$scope = false;
            }
        }
    }

    protected function applyDisabled(&$table, &$fieldTable)
    {
        foreach ($this->getScopeList() as $scope) {
            $d = $this->getMetadata()->get('scopes.' . $scope);
            if (!empty($d['disabled']) || !empty($d['portalDisabled'])) {
                $table->$scope = false;
                unset($fieldTable->$scope);
            }
        }
    }

    protected function applyAdditional(&$table, &$fieldTable, &$valuePermissionLists)
    {
    }
}

