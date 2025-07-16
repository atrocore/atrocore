/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/associated-records', ['views/record/panels/records-in-groups'], (Dep) => Dep.extend({

        groupScope: 'Association',
        disableSelect: true,

        template: 'record/panels/associated-records',

        rowActionsView: 'views/record/row-actions/relationship-no-remove',

        groups: [],

        disableCollectionFetch: true,

        unlinkGroup: true,

        getCreateLink() {
            return `associatedMain${this.scope}s`;
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.scope = this.model.name

            this.defs.recordListView = this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || "views/record/list"

            this.listenTo(this, 'groups-rendered', () => {
                setTimeout(() => this.regulateTableSizes(), 500)
            });
        },

        data() {
            return {
                ...Dep.prototype.data.call(this),
                unlinkGroup: this.unlinkGroup
            }
        },

        getModel(data, evt) {
            const idx = $(evt.target).closest('.group').index()
            const key = this.groups[idx].key
            return this.getView(key).collection.get(data.cid)
        },

        afterGroupRender() {
            this.groups.forEach(group => {
                const groupCollection = this.getView(group.key).collection;
                groupCollection.forEach(item => {
                    item = item.relationModel
                    if (this.collection.get(item.get('id'))) {
                        this.collection.remove(item.get('id'));
                    }
                    this.collection.add(item);
                });
            })

            this.collection.total = this.collection.length
            this.collection.trigger('update-total', this.collection)
            this.trigger('after-groupPanels-rendered')
        },

        getLayoutLink() {
            return `${this.scope}.associated${this.scope}s`
        },

        getAdditionalListOptions() {
            return {
                useRelationModelOnEdit: true
            }
        },

        initGroupCollection(group, groupCollection, callback) {
            this.getHelper().layoutManager.get(this.scope, this.layoutName, this.getLayoutLink(), null, data => {
                groupCollection.url = this.model.name + '/' + this.model.id + '/' + this.link;
                groupCollection.collectionOnly = true;
                groupCollection.maxSize = 999
                groupCollection.data.whereRelation = [
                    {
                        type: 'equals',
                        attribute: 'associationId',
                        value: group.id
                    }
                ]
                let list = [];
                data.layout.forEach(item => {
                    if (item.name) {
                        let field = item.name;
                        let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                        if (fieldType) {
                            this.getFieldManager().getAttributeList(fieldType, field).forEach(attribute => {
                                if (fieldType === 'link' || fieldType === 'linkMultiple') {
                                    const foreignEntity = this.getMetadata().get(['entityDefs', this.scope, 'links', field, 'entity']);
                                    let foreignName = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'foreignName']);
                                    if (foreignEntity && this.getMetadata().get(['entityDefs', foreignEntity, 'fields', 'name'])) {
                                        foreignName = 'name';
                                    }

                                    if (!foreignName && (attribute.endsWith('Name') || attribute.endsWith('Names'))) {
                                        return;
                                    }
                                }

                                list.push(attribute);
                            });
                        }
                    }
                });
                groupCollection.data.select = list.join(',')

                groupCollection.fetch().success(() => {
                    callback();
                });
            })
        },

        fetchCollectionGroups(callback) {
            const data = {
                where: [
                    {
                        type: 'bool',
                        value: ['usedAssociations'],
                        data: {
                            usedAssociations: {
                                scope: this.scope,
                                mainRecordId: this.model.id
                            }
                        }
                    }
                ]
            }
            this.ajaxGetRequest('Association', data).then(data => {
                this.groups = data.list.map(row => ({ id: row.id, key: row.id, label: this.getAssociationLabel(row) }));
                callback();
            });
        },

        getAssociationLabel(association) {
            return this.translate('associatingTitle', 'labels', this.scope).replace(':name', `<a href="#Association/view/${association.id}"><strong>${association.name}</strong></a>`)
        },

        deleteEntities(groupId) {
            const data = { mainRecordId: this.model.id }
            if (groupId) data.associationId = groupId
            this.ajaxPostRequest(`Associated${this.scope}/action/RemoveAssociates`, data)
                .done(response => {
                    this.notify(false);
                    this.notify('Removed', 'success');
                    this.model.trigger('after:unrelate');
                });
        },

        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                this.deleteEntities()
            });
        },

        actionUnlinkGroup(data) {
            let id = data.id;
            if (!id) {
                return;
            }

            let group = this.groups.find(group => group.id === id);
            if (!group) {
                return;
            }

            this.confirm({
                message: this.translate('removeRelatedRecords', 'messages', this.scope),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify('removing');
                this.deleteEntities(group.id)
            }, this);
        }
    })
);