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

use Atro\Entities\Role as RoleEntity;
use Espo\Core\AclManager;
use Espo\ORM\Entity;

class Role extends \Espo\Core\ORM\Repositories\RDB
{
    public function getScopesData(RoleEntity $role): \stdClass
    {
        $res = [
            'Product' => [
                'create' => 'no',
                'read' => 'all',
                'edit' => 'no',
                'delete' => 'no',
                'stream' => 'no',
            ]
        ];

//        $res = new \stdClass();
//        $res->Product->create = "no";
//        $res->Product->read = "no";
//        $res->Product->edit = "no";
//        $res->Product->delete = "no";
//        $res->Product->stream = "no";

        // {"Boo":{"create":"yes","read":"own","edit":"own","delete":"own","stream":"no"},"Brand":{"create":"yes","read":"all","edit":"all","delete":"all"},"Product":{"create":"yes","read":"all","edit":"all","delete":"all","stream":"all"},"UserProfile":{"edit":"own"},"User":{"read":"all"}}

        return json_decode(json_encode($res));
    }

    public function getScopesFieldsData(RoleEntity $role): \stdClass
    {
        $res = new \stdClass();

        // {"Account":{},"ActionHistoryRecord":{},"Action":{},"ActionSetLinker":{},"Address":{},"AddressAccount":{},"AddressContact":{},"ReportAggregation":{},"AssociatedProduct":{},"Association":{},"AttributeGroup":{},"AttributeRule":{},"Attribute":{},"Boo":{},"BooHierarchy":{},"BrandFile":{},"Brand":{},"BudgetItem":{},"Catalog":{},"Category":{},"CategoryFile":{},"CategoryChannel":{},"CategoryHierarchy":{},"Channel":{},"ClassificationAttribute":{},"ClassificationHierarchy":{},"Classification":{},"Contact":{},"ContactAccount":{},"ContentItem":{},"Country":{},"CustomEntity":{},"DynamicRelation":{},"DynamicRelationSelection":{},"EmailTemplate":{},"Email":{},"ExportFeed":{},"ExportJob":{},"ExtensibleEnumExtensibleEnumOption":{},"FileType":{},"File":{},"FolderHierarchy":{},"Folder":{},"Foo":{},"FooAsset":{},"FooBrand":{},"FooClassification":{},"FooProduct":{},"Hierarchy2Hierarchy":{},"Hierarchy2":{},"ImportHttpHeader":{},"ImportFeed":{},"ImportJob":{},"ImportJobFile":{},"Issue":{},"Job":{},"LayoutProfile":{},"ExtensibleEnumOption":{},"ExtensibleEnum":{},"Measure":{},"Milestone":{},"NotificationProfile":{},"NotificationRule":{},"NotificationTemplate":{},"PreviewTemplate":{},"ProductChannel":{},"ProductFile":{},"ProductSerie":{},"ProductCategory":{},"ProductClassification":{},"ProductContentItem":{},"ProductHierarchy":{},"Product":{"isActive":{"read":"no","edit":"no"}},"Project":{},"Report":{},"SavedSearch":{},"Sharing":{},"Sheet":{},"Storage":{},"Synchronization":{},"Tax":{},"Team":{},"Test11":{},"Test4":{},"Unit":{},"UserProfile":{},"User":{},"VariantSpecificProductAttribute":{},"Zoo":{}}

        return $res;
    }

    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        $this
            ->getAclManager()
            ->clearAclCache();
    }

    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected function getAclManager(): AclManager
    {
        return $this->getInjection('container')->get('aclManager');
    }
}
