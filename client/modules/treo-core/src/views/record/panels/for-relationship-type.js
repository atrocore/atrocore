/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/record/panels/for-relationship-type', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        rowActionsView: 'treo-core:views/record/row-actions/for-relationship-type',

        rowActionsColumnWidth: 70,

        setup() {
            this.defs.select = false;

            Dep.prototype.setup.call(this);

            const relationshipScope = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.panelName, 'entity']);

            let relationshipEntities = this.getRelationshipEntities();

            if (relationshipEntities.length === 2) {
                this.actionList.unshift({
                    label: 'Select',
                    action: 'selectRelatedEntity',
                    data: {
                        link: this.panelName,
                        scope: relationshipEntities.filter(entity => entity !== this.model.urlRoot).shift(),
                        afterSelectCallback: "createRelationshipEntities",
                        massRelateDisabled: false
                    },
                    acl: 'create',
                    aclScope: relationshipScope
                });
            }

            if (!this.model.get('isRoot')) {
                this.actionList.push({
                    label: 'inheritAll',
                    action: 'inheritAll',
                    data: {
                        "relationshipScope": relationshipScope
                    },
                    acl: 'edit',
                    aclScope: relationshipScope
                });
            }

            this.actionList.push({
                label: 'deleteAll',
                action: 'deleteAllRelationshipEntities',
                data: {
                    "relationshipScope": relationshipScope
                },
                acl: 'delete',
                aclScope: relationshipScope
            });
        },

        getRelationshipEntities() {
            const relationshipScope = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.panelName, 'entity']);
            let relationshipEntities = [];
            $.each(this.getMetadata().get(['entityDefs', relationshipScope, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.relationshipField === true) {
                    relationshipEntities.push(this.getMetadata().get(['entityDefs', relationshipScope, 'links', field, 'entity']));
                }
            });

            return relationshipEntities;
        },

        createRelationshipEntities(selectObj) {
            if (Array.isArray(selectObj)) {
                this.createRelationshipEntitiesViaWhere([
                    {
                        type: 'equals',
                        attribute: 'id',
                        value: selectObj.map(o => o.id)
                    }
                ])
            } else {
                this.createRelationshipEntitiesViaWhere(selectObj.where)
            }
        },

        createRelationshipEntitiesViaWhere(foreignWhere) {
            this.notify('Please wait...');

            this.ajaxPostRequest(`${this.model.name}/${this.link}/relation`, {
                where: [
                    {
                        type: "equals",
                        attribute: "id",
                        value: this.model.id
                    }
                ],
                foreignWhere: foreignWhere,
            }).then((response) => {
                this.notify(response.message, 'success');
                this.actionRefresh();
                this.model.trigger('after:relate', this.panelName);
            });
        },

        actionInheritAll(data) {
            this.confirm(this.translate('inheritAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                $.ajax({
                    url: `${data.relationshipScope}/action/inheritAll`,
                    type: 'POST',
                    data: JSON.stringify({
                        id: this.model.id
                    }),
                }).done(() => {
                    this.notify(false);
                    this.notify('Linked', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:relate', this.panelName);
                });
            });
        },

        actionInheritRelationship(data) {
            this.notify('Please wait...');
            $.ajax({
                url: `${data.entity}/action/inherit`,
                type: 'POST',
                data: JSON.stringify({
                    id: data.id
                }),
            }).done(() => {
                this.notify(false);
                this.notify('Linked', 'success');
                this.collection.fetch();
                this.model.trigger('after:relate', this.panelName);
            });
        },

        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                $.ajax({
                    url: `${this.model.name}/${this.link}/relation`,
                    data: JSON.stringify({
                        where: [
                            {
                                type: "equals",
                                attribute: "id",
                                value: this.model.id
                            }
                        ],
                        foreignWhere: [],
                    }),
                    type: 'DELETE',
                    contentType: 'application/json',
                }).done(response => {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:unrelate');
                });
            });
        },

    });
});

