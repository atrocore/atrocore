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

        url: null,

        scope: null,

        readOnly: false,

        fetchOnModelAfterRelate: false,

        init: function () {
            Dep.prototype.init.call(this);
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

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
                        buttonsDisabled: true,
                        el: this.options.el + ' .list-container',
                        skipBuildRows: true
                    }, function (view) {
                        view.getSelectAttributeList(function (selectAttributeList) {
                            if (selectAttributeList) {
                                collection.data.select = selectAttributeList.join(',');
                            }
                            collection.fetch();
                        }.bind(this));

                        view.listenTo(view, 'after:save', function () {
                            let parent = this.getParentView();

                            if (parent && 'link' in parent) {
                                $('a[data-name="' + parent.link + '"]').click();
                            }
                        });
                    });
                }, this);

                this.wait(false);
            }, this);

            this.setupFilterActions();
        },

        setupListLayout: function () {},

        setupActions: function () {},

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
                var $el = this.$el.closest('.panel').find('[data-name="'+item+'"] span');
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
            var id = data.id;

            this.confirm({
                message: this.translate('unlinkRecordConfirmation', 'messages'),
                confirmText: this.translate('Unlink')
            }, function () {
                var model = this.collection.get(id);
                this.notify('Unlinking...');
                $.ajax({
                    url: this.collection.url,
                    type: 'DELETE',
                    data: JSON.stringify({
                        id: id
                    }),
                    contentType: 'application/json',
                    success: function () {
                        this.notify('Unlinked', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate');
                    }.bind(this),
                    error: function () {
                        this.notify('Error occurred', 'error');
                    }.bind(this),
                });
            }, this);
        },

        actionRemoveRelated: function (data) {
            var id = data.id;

            this.confirm({
                message: this.translate('removeRecordConfirmation', 'messages'),
                confirmText: this.translate('Remove')
            }, function () {
                var model = this.collection.get(id);
                this.notify('Removing...');
                model.destroy({
                    success: function () {
                        this.notify('Removed', 'success');
                        this.collection.fetch();
                        this.model.trigger('after:unrelate');
                    }.bind(this),
                });
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

