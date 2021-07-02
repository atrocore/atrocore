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

Espo.define('treo-core:views/record/panels/relationship', ['class-replace!treo-core:views/record/panels/relationship', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        filtersLayoutLoaded: false,

        setup() {
            Dep.prototype.setup.call(this);

            this.addReadyCondition(() => {
                return this.filtersLayoutLoaded;
            });

            this.getHelper().layoutManager.get(this.scope, 'filters', layout => {
                this.filtersLayoutLoaded = true;
                let foreign = this.model.getLinkParam(this.link, 'foreign');

                if (foreign && layout.includes(foreign)) {
                    this.actionList.push({
                        label: 'showFullList',
                        action: this.defs.showFullListAction || 'showFullList',
                        data: {
                            modelId: this.model.get('id'),
                            modelName: this.model.get('name')
                        }
                    });
                }

                this.tryReady();
            });

            var select = this.actionList.find(item => item.action === (this.defs.selectAction || 'selectRelated'));
            if (select) {
                select.data = {
                    link: this.link,
                    scope: this.scope,
                    boolFilterListCallback: 'getSelectBoolFilterList',
                    boolFilterDataCallback: 'getSelectBoolFilterData',
                    primaryFilterName: this.defs.selectPrimaryFilterName || null
                };
            }
        },

        actionShowFullList(data) {
            let entity = this.model.getLinkParam(this.link, 'entity');
            let foreign = this.model.getLinkParam(this.link, 'foreign');
            let defs = this.getMetadata().get(['entityDefs', entity, 'fields', foreign]) || {};
            let type = defs.type;

            let advanced = {};
            if (type === 'link') {
                advanced = {
                    [foreign]: {
                        type: 'equals',
                        field: foreign + 'Id',
                        value: data.modelId,
                        data: {
                            type: 'is',
                            idValue: data.modelId,
                            nameValue: data.modelName
                        }
                    }
                }
            } else if (type === 'linkMultiple') {
                advanced = {
                    [foreign]: {
                        type: 'linkedWith',
                        value: [data.modelId],
                        nameHash: {[data.modelId]: data.modelName},
                        data: {
                            type: 'anyOf'
                        }
                    }
                }
            }

            let params = {
                showFullListFilter: true,
                advanced: advanced
            };

            this.getRouter().navigate(`#${this.scope}`, {trigger: true});
            this.getRouter().dispatch(this.scope, 'list', params);
        },

        actionUnlinkRelated(data) {
            let id = data.id;
            let scope = this.collection.url.split('/').shift();
            let link = this.collection.url.split('/').pop();
            let message = this.translate('unlinkRecordConfirmation', 'messages');

            const unlinkConfirm = this.getMetadata().get(`clientDefs.${scope}.relationshipPanels.${link}.unlinkConfirm`) || false;
            if (unlinkConfirm) {
                let parts = unlinkConfirm.split('.');
                message = this.translate(parts[2], parts[1], parts[0]);
            }

            this.confirm({
                message: message,
                confirmText: this.translate('Unlink')
            }, () => {
                let model = this.collection.get(id);
                this.notify('Unlinking...');
                $.ajax({
                    url: this.collection.url,
                    type: 'DELETE',
                    data: JSON.stringify({
                        id: id
                    }),
                    contentType: 'application/json',
                    success: () => {
                        this.notify('Unlinked', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link);
                    },
                    error: () => {
                        this.notify('Error occurred', 'error');
                    },
                });
            });
        },

        actionRemoveRelated(data) {
            let id = data.id;

            this.confirm({
                message: this.translate('removeRecordConfirmation', 'messages'),
                confirmText: this.translate('Remove')
            }, () => {
                let model = this.collection.get(id);
                this.notify('Removing...');
                model.destroy({
                    success: () => {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link);
                    },

                    error: () => {
                        this.collection.push(model);
                    }
                });
            });
        },

        getSelectBoolFilterData(boolFilterList) {
            let data = {};
            if (Array.isArray(boolFilterList)) {
                boolFilterList.forEach(item => {
                    if (this.boolFilterData && typeof this.boolFilterData[item] === 'function') {
                        data[item] = this.boolFilterData[item].call(this);
                    }
                });
            }
            return data;
        },

        getSelectBoolFilterList() {
            return this.defs.selectBoolFilterList || null
        },

    });
});