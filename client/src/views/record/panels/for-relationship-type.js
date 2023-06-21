/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('views/record/panels/for-relationship-type', 'views/record/panels/relationship', Dep => {

    return Dep.extend({

        rowActionsView: 'views/record/row-actions/relationship-no-unlink',

        setup() {
            this.defs.select = false;

            Dep.prototype.setup.call(this);

            const relationshipScope = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.panelName, 'entity']);

            let relationshipEntities = [];
            $.each(this.getMetadata().get(['entityDefs', relationshipScope, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.relationshipField === true) {
                    relationshipEntities.push(this.getMetadata().get(['entityDefs', relationshipScope, 'links', field, 'entity']));
                }
            });

            if (relationshipEntities.length === 2) {
                this.actionList.unshift({
                    label: 'Select',
                    action: 'selectRelatedEntity',
                    data: {
                        link: this.panelName,
                        scope: relationshipEntities.filter(entity => entity !== this.model.urlRoot).shift(),
                        afterSelectCallback: "createRelationshipEntitiesViaIds",
                        massRelateDisabled: true
                    },
                    acl: 'create',
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

        createRelationshipEntitiesViaIds(selectObj) {
            const relationshipScope = this.getMetadata().get(['entityDefs', this.model.name, 'links', this.panelName, 'entity']);

            let from = null;
            let to = null;
            $.each(this.getMetadata().get(['entityDefs', relationshipScope, 'fields']), (field, fieldDefs) => {
                if (fieldDefs.relationshipField === true) {
                    if (this.getMetadata().get(['entityDefs', relationshipScope, 'links', field, 'entity']) === this.model.name) {
                        from = field;
                    } else {
                        to = field;
                    }
                }
            });

            if (!from || !to) {
                return;
            }

            let promises = [];
            selectObj.forEach(model => {
                let data = {"_silentMode": true};
                data[from + 'Id'] = this.model.id;
                data[to + 'Id'] = model.id;
                promises.push(new Promise(resolve => {
                    this.ajaxPostRequest(relationshipScope, data).then(response => {
                        resolve();
                    });
                }));
            });

            Promise.all(promises).then(() => {
                this.notify('Created', 'success');
                this.actionRefresh();
                this.model.trigger('after:relate', this.panelName);
            });
        },

        actionDeleteAllRelationshipEntities(data) {
            this.confirm(this.translate('deleteAllConfirmation', 'messages'), () => {
                this.notify('Please wait...');
                this.ajaxGetRequest(`${this.model.name}/${this.model.id}/${this.panelName}?select=id&maxSize=9999&offset=0`).then(response => {
                    if (response.total > 0) {
                        let promises = [];
                        response.list.forEach(item => {
                            promises.push(new Promise(resolve => {
                                $.ajax({
                                    url: `${data.relationshipScope}/${item.id}`,
                                    type: 'DELETE',
                                }).done(response => {
                                    resolve();
                                });
                            }));
                        });
                        Promise.all(promises).then(() => {
                            this.notify(false);
                            this.notify('Removed', 'success');
                            this.collection.fetch();
                            this.model.trigger('after:unrelate');
                        });
                    } else {
                        this.notify(false);
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                    }
                });
            });
        },

    });
});

