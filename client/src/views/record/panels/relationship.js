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

Espo.define('views/record/panels/relationship', ['views/record/panels/bottom', 'search-manager'], function (Dep, SearchManager) {

    return Dep.extend({

        template: 'record/panels/relationship',

        rowActionsView: 'views/record/row-actions/relationship',

        rowActionsColumnWidth: 25,

        url: null,

        scope: null,

        readOnly: false,

        fetchOnModelAfterRelate: false,

        dragableListRows: undefined,

        listRowsOrderSaveUrl: undefined,

        filtersLayoutLoaded: false,

        boolFilterData: {
            notParents() {
                return this.model.get('id');
            },
            notChildren() {
                return this.model.get('id');
            }
        },

        init: function () {
            Dep.prototype.init.call(this);
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

            if (this.getMetadata().get(`scopes.${this.model.name}.relationInheritance`) === true && this.model.get('isRoot') === false) {
                let unInheritedRelations = ['parents', 'children'];
                (this.getMetadata().get(`scopes.${this.model.name}.unInheritedRelations`) || []).forEach(field => {
                    unInheritedRelations.push(field);
                });
                if (!unInheritedRelations.includes(this.link)) {
                    this.rowActionsColumnWidth = 70;
                }
            }

            if (!this.scope && !(this.link in this.model.defs.links)) {
                throw new Error('Link \'' + this.link + '\' is not defined in model \'' + this.model.name + '\'');
            }
            this.title = this.title || this.translate(this.link, 'links', this.model.name);
            this.scope = this.scope || this.model.defs.links[this.link].entity;

            if (!this.getConfig().get('scopeColorsDisabled')) {
                var iconHtml = this.getHelper().getScopeColorIconHtml(this.scope);
                if (iconHtml) {
                    if (this.defs.label) {
                        this.titleHtml = iconHtml + this.translate(this.defs.label, 'labels', this.scope);
                    } else {
                        this.titleHtml = iconHtml + this.title;
                    }
                }
            }

            var url = this.url || this.model.name + '/' + this.model.id + '/' + this.link;

            if (!this.readOnly && !this.defs.readOnly) {
                if (!('create' in this.defs)) {
                    this.defs.create = true;
                }
                if (!('select' in this.defs)) {
                    this.defs.select = true;
                }
            }

            this.filterList = this.defs.filterList || this.filterList || null;

            if (this.filterList && this.filterList.length) {
                this.filter = this.getStoredFilter();
            }

            if (this.defs.create) {
                if (this.getAcl().check(this.scope, 'create') && !~['User', 'Team'].indexOf()) {
                    this.buttonList.push({
                        title: 'Create',
                        action: this.defs.createAction || 'createRelated',
                        link: this.link,
                        acl: 'create',
                        aclScope: this.scope,
                        html: '<span class="fas fa-plus"></span>',
                        data: {
                            link: this.link,
                        }
                    });
                }
            }

            if (this.defs.select) {
                var data = {link: this.link};
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }

                this.actionList.unshift({
                    label: 'Select',
                    action: this.defs.selectAction || 'selectRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });
            }

            if (this.isInheritingRelation() && this.model.get('isRoot') !== true) {
                this.actionList.push({
                    label: 'inheritAll',
                    action: 'inheritAll',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });
            }

            if (this.defs.select === true && this.defs.unlinkAll !== false) {
                this.actionList.push({
                    label: 'unlinkAll',
                    action: 'unlinkAllRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });
            }

            this.setupActions();

            var layoutName = 'listSmall';
            this.setupListLayout();

            if (this.listLayoutName) {
                layoutName = this.listLayoutName;
            }

            var listLayout = null;
            var layout = this.defs.layout || null;
            if (layout) {
                if (typeof layout == 'string') {
                    layoutName = layout;
                } else {
                    layoutName = 'listRelationshipCustom';
                    listLayout = layout;
                }
            }

            var sortBy = this.defs.sortBy || null;
            var asc = this.defs.asc || null;

            if (this.defs.orderBy) {
                sortBy = this.defs.orderBy;
                asc = true;
                if (this.defs.orderDirection) {
                    if (this.defs.orderDirection && (this.defs.orderDirection === true || this.defs.orderDirection.toLowerCase() === 'DESC')) {
                        asc = false;
                    }
                }
            }

            this.wait(true);
            this.getCollectionFactory().create(this.scope, function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 5;
                if (this.defs.dragDrop) {
                    collection.maxSize = 9999;
                    if (this.defs.dragDrop.maxSize) {
                        collection.maxSize = this.defs.dragDrop.maxSize;
                    }
                }

                if (this.defs.filters) {
                    var searchManager = new SearchManager(collection, 'listRelationship', false, this.getDateTime());
                    searchManager.setAdvanced(this.defs.filters);
                    collection.where = searchManager.getWhere();
                }

                collection.url = collection.urlRoot = url;
                if (sortBy) {
                    collection.sortBy = sortBy;
                }
                if (asc) {
                    collection.asc = asc;
                }
                this.collection = collection;

                this.setFilter(this.filter);

                if (this.fetchOnModelAfterRelate) {
                    this.listenTo(this.model, 'after:relate', function () {
                        collection.fetch();
                    }, this);
                }

                this.listenTo(this.model, 'update-all', function () {
                    collection.fetch();
                }, this);

                var viewName = this.defs.recordListView || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.list') || 'Record.List';

                this.once('after:render', function () {
                    this.createView('list', viewName, {
                        collection: collection,
                        layoutName: layoutName,
                        listLayout: listLayout,
                        checkboxes: false,
                        rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                        rowActionsColumnWidth: this.rowActionsColumnWidth,
                        buttonsDisabled: true,
                        el: this.options.el + ' .list-container',
                        skipBuildRows: true,
                        dragableListRows: this.dragableListRows,
                        listRowsOrderSaveUrl: this.listRowsOrderSaveUrl,
                        panelView: this,
                    }, function (view) {
                        view.getSelectAttributeList(function (selectAttributeList) {
                            if (selectAttributeList) {
                                collection.data.select = selectAttributeList.join(',');
                            }
                            collection.fetch();
                        }.bind(this));
                    });
                    this.setupTotal.call(this)
                }, this);
                this.wait(false);
            }, this);

            this.setupFilterActions();

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

        setupTotal() {
            let $btnGroup = this.$el.parent().find('.panel-heading .btn-group');
            $btnGroup.find('.list-total').remove();

            const $buttonHtml = $('<button type="button" style="width: auto" class="btn btn-default btn-sm action list-total"><span style="line-height: 0px"></span></button>');
            $btnGroup.prepend($buttonHtml);
            $buttonHtml.hide()

            this.listenTo(this.collection, 'update update-total', () => {
                const total = this.collection.total || this.collection.length
                $buttonHtml.find('span').text(`${this.translate('Shown', 'labels', 'Global')}: ${this.collection.length} | ${this.translate('Total', 'labels', 'Global')}: ${total}`)
                $buttonHtml.show()
            })
        },

        setupListLayout: function () {
        },

        setupActions: function () {
        },

        setupFilterActions: function () {
            if (this.filterList && this.filterList.length) {
                if (this.actionList.length) {
                    this.actionList.unshift(false);
                }
                this.filterList.slice(0).reverse().forEach(function (item) {
                    var selected = false;
                    if (item == 'all') {
                        selected = !this.filter;
                    } else {
                        selected = item === this.filter;
                    }
                    this.actionList.unshift({
                        action: 'selectFilter',
                        html: '<span class="fas fa-check pull-right' + (!selected ? ' hidden' : '') + '"></span>' + this.translate(item, 'presetFilters', this.scope),
                        data: {
                            name: item
                        }
                    });
                }, this);
            }
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

        getUnInheritedRelations: function () {
            const scope = this.model.urlRoot;

            let unInheritedRelations = [];

            (this.getMetadata().get(`app.nonInheritedRelations`) || []).forEach(field => {
                unInheritedRelations.push(field);
            });

            (this.getMetadata().get(`scopes.${scope}.mandatoryUnInheritedRelations`) || []).forEach(field => {
                unInheritedRelations.push(field);
            });

            (this.getMetadata().get(`scopes.${scope}.unInheritedRelations`) || []).forEach(field => {
                unInheritedRelations.push(field);
            });

            $.each(this.getMetadata().get(`entityDefs.${scope}.links`), (link, linkDefs) => {
                if (linkDefs.type && linkDefs.type === 'hasMany') {
                    if (!linkDefs.relationName) {
                        unInheritedRelations.push(link);
                    }
                }
            });

            return unInheritedRelations;
        },

        isInheritingRelation: function () {
            const scope = this.model.urlRoot;
            const link = this.link;

            if (this.getMetadata().get(`scopes.${scope}.type`) === 'Hierarchy' && this.getMetadata().get(`scopes.${scope}.relationInheritance`) === true) {
                let unInheritedRelations = this.getUnInheritedRelations();
                if (!unInheritedRelations.includes(link) && this.getMetadata().get(['entityDefs', scope, 'links', link, 'relationName'])) {
                    return true;
                }
            }

            return false;
        },

        getStoredFilter: function () {
            var key = 'panelFilter' + this.scope + '-' + this.panelName;
            return this.getStorage().get('state', key) || null;
        },

        storeFilter: function (filter) {
            var key = 'panelFilter' + this.scope + '-' + this.panelName;
            if (filter) {
                this.getStorage().set('state', key, filter);
            } else {
                this.getStorage().clear('state', key);
            }
        },

        setFilter: function (filter) {
            this.collection.data.primaryFilter = null;
            if (filter) {
                this.collection.data.primaryFilter = filter;
            }
        },

        actionSelectFilter: function (data) {
            var filter = data.name;
            var filterInternal = filter;
            if (filter == 'all') {
                filterInternal = false;
            }
            this.storeFilter(filterInternal);
            this.setFilter(filterInternal);

            this.filterList.forEach(function (item) {
                var $el = this.$el.closest('.panel').find('[data-name="' + item + '"] span');
                if (item === filter) {
                    $el.removeClass('hidden');
                } else {
                    $el.addClass('hidden');
                }
            }, this);
            this.collection.reset();
            this.collection.fetch();
        },

        actionRefresh: function () {
            this.collection.fetch();
        },

        actionViewRelated: function (data) {
            var id = data.id;
            var scope = this.collection.get(id).name;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.detail') || 'views/modals/detail';

            this.notify('Loading...');
            this.createView('quickDetail', viewName, {
                scope: scope,
                id: id,
                model: this.collection.get(id),
            }, function (view) {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });
                view.render();
                view.once('after:save', function () {
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        actionCreateRelated: function (data) {
            data = data || {};

            let link = data.link;
            let scope = this.model.defs['links'][link].entity;
            let foreignLink = this.model.defs['links'][link].foreign;

            this.model.defs['_relationName'] = link;

            let viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            let attributes = {};
            this.model.trigger('prepareAttributesForCreateRelated', attributes, link, preparedAttributes => {
                attributes = preparedAttributes;
            });

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: scope,
                fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                relate: {
                    model: this.model,
                    link: foreignLink,
                },
                attributes: attributes,
            }, view => {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', () => {
                    this.model.trigger('updateRelationshipPanel', link);
                    this.collection.fetch();
                    this.model.trigger('after:relate', link);
                });
            });
        },

        actionEditRelated: function (data) {
            var id = data.id;
            var scope = this.collection.get(id).name;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            this.notify('Loading...');
            this.createView('quickEdit', viewName, {
                scope: scope,
                id: id
            }, function (view) {
                view.once('after:render', function () {
                    Espo.Ui.notify(false);
                });
                view.render();
                view.once('after:save', function () {
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        actionUnlinkRelated: function (data) {
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
                        this.model.trigger('after:unrelate', this.link, this.defs);
                    },
                    error: () => {
                        this.notify('Error occurred', 'error');
                    },
                });
            });
        },

        actionRemoveRelated: function (data) {
            let id = data.id;

            let message = 'Global.messages.removeRecordConfirmation';
            if (this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy') {
                message = 'Global.messages.removeRecordConfirmationHierarchically';
            }

            let scopeMessage = this.getMetadata().get(`clientDefs.${this.scope}.deleteConfirmation`);
            if (scopeMessage) {
                message = scopeMessage;
            }

            let model = this.collection.get(id);

            let parts = message.split('.');

            this.confirm({
                message: (this.translate(parts.pop(), parts.pop(), parts.pop())).replace('{{name}}', model.get('name')),
                confirmText: this.translate('Remove')
            }, () => {
                this.notify('removing');
                model.destroy({
                    success: () => {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate', this.link, this.defs);
                    },

                    error: () => {
                        this.collection.push(model);
                    }
                });
            });
        },

        actionInheritAll: function (data) {
            this.confirm(this.translate('inheritAllConfirmation', 'messages'), function () {
                this.notify('Please wait...');
                $.ajax({
                    url: this.model.name + '/action/inheritAll',
                    type: 'POST',
                    data: JSON.stringify({
                        link: data.link,
                        id: this.model.id
                    }),
                }).done(function () {
                    this.notify(false);
                    this.notify('Linked', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:relate', data.link);
                }.bind(this));
            }, this);
        },

        actionUnlinkAllRelated: function (data) {
            this.confirm(this.translate('unlinkAllConfirmation', 'messages'), function () {
                this.notify('Please wait...');
                $.ajax({
                    url: this.model.name + '/action/unlinkAll',
                    type: 'POST',
                    data: JSON.stringify({
                        link: data.link,
                        id: this.model.id
                    }),
                }).done(function () {
                    this.notify(false);
                    this.notify('Unlinked', 'success');
                    this.collection.fetch();
                    this.model.trigger('after:unrelate');
                }.bind(this));
            }, this);
        },

    });
});

