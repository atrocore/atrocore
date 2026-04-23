
---
title: Access Control Lists
---

In AtroCore, the **Access Control List (ACL)** is a fundamental system for managing user permissions. It defines **granular permissions** for different user roles, ensuring that users can only access the data and perform the actions they are authorized to.

The ACL is managed through the **`Acl` service** available in the service container. To enable ACL configuration for an entity, you must set the `acl` parameter's `scope` to `true` in its metadata.

## Checking Permissions

You can check a user's permissions for an entity or action using the `acl` service. This is most commonly done with the `check()` method.

```php
/** @var \Espo\Core\Acl $acl */
$acl = $container->get('acl');

// Check standard actions on the 'Product' entity
$canReadProduct = $acl->check('Product', 'read');
$canCreateProduct = $acl->check('Product', 'create');
$canEditProduct = $acl->check('Product', 'edit');
$canDeleteProduct = $acl->check('Product', 'delete');

//check if the user has access Product changes activities
$canDeleteProduct = $acl->check('Product', 'stream');

// Specific action when Attribute value is supported
$canCreateAttributeValue = $acl->check('Product', 'createAttributeValue');
$canDeleteAttributeValue = $acl->check('Product', 'deleteAttributeValue');
```

The ACL for any user is configured in the administration panel. For more information, refer to the documentation on [roles](../../../01.atrocore/03.administration/14.access-management/03.roles).

-----

## Customizing ACL Behavior

Sometimes, you need to implement **custom ACL behavior** for an entity, regardless of a user's role configuration or for entities where ACL settings don't apply. You can achieve this by creating a custom ACL class for that entity.

To do this, create a file at `Acl/{EntityName}.php` and extend the `Atro\Acl\Base` class. Within this new class, you can override any of the `check` methods to implement your specific verification logic.

Here is a template for a custom ACL class:

```php
<?php

namespace Atro\Acl;

use Espo\Core\Acl\Base;
use Espo\Entities\User;
use Espo\ORM\Entity;

class {EntityName} extends Base
{
    /**
     * Checks if a user has access to the specified scope.
     * Overriding this method allows for custom permission logic at the scope level.
     */
    public function checkScope(User $user, $data, $action = null, Entity $entity = null, $entityAccessData = array())
    {
        // Example: Only allow users with a specific role to access this scope and change the name
        if ($this->checkRole($user, 'my-special-role') && $entity->isAttributeChanged('name')) {
            return true;
        }

        return parent::checkScope($user, $data, $action, $entity, $entityAccessData);
    }

    /**
     * Checks if a user can read a specific entity.
     * You can add custom conditions here, e.g., only allowing the entity's owner to read it.
     */
    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        // Example: Allow the owner of the entity to read it, regardless of their role.
        if ($entity->get('assignedUserId') === $user->getId()) {
            return true;
        }

        return parent::checkEntityRead($user, $entity, $data);
    }

    /**
     * Checks if a user can create a new entity.
     */
    public function checkEntityCreate(User $user, Entity $entity, $data)
    {
        return parent::checkEntityCreate($user, $entity, $data);
    }

    /**
     * Checks if a user can edit an existing entity.
     */
    public function checkEntityEdit(User $user, Entity $entity, $data)
    {
        return parent::checkEntityEdit($user, $entity, $data);
    }

    /**
     * Checks if a user can delete an entity.
     */
    public function checkEntityDelete(User $user, Entity $entity, $data)
    {
        return parent::checkEntityDelete($user, $entity, $data);
    }

    /**
    * add your logic to check for the role
    */
    protected function checkRole(User $user, $roleId): bool
    {
        //todo
    }
}
```
