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

Espo.define('views/detail', 'views/main', function (Dep) {

    return Dep.extend({

        template: 'detail',

        scope: null,

        name: 'Detail',

        optionsToPass: ['attributes', 'returnUrl', 'returnDispatchParams', 'rootUrl'],

        headerView: 'views/header',

        recordView: 'views/record/detail',

        relatedAttributeMap: {},

        relatedAttributeFunctions: {},

        selectRelatedFilters: {},

        selectPrimaryFilterNames: {},

        selectBoolFilterLists: {},

        boolFilterData: {},

        navigateButtonsDisabled: false,

        navigationButtons: {
            next: {
                html: '<span class="fas fa-chevron-right"></span>',
                title: 'Next Entry',
                disabled: true
            },
            previous: {
                html: '<span class="fas fa-chevron-left"></span>',
                title: 'Previous Entry',
                disabled: true
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.headerView = this.options.headerView || this.headerView;
            this.recordView = this.options.recordView || this.recordView;
            this.navigateButtonsDisabled = this.options.navigateButtonsDisabled || this.navigateButtonsDisabled;

            this.setupHeader();
            this.setupRecord();

            this.listenTo(this.model, 'prepareAttributesForCreateRelated', (attributes, link, callback) => {
                if (this.relatedAttributeFunctions[link] && typeof this.relatedAttributeFunctions[link] == 'function') {
                    attributes = _.extend(this.relatedAttributeFunctions[link].call(this), attributes);
                }
                Object.keys(this.relatedAttributeMap[link] || {}).forEach(function (attr) {
                    attributes[this.relatedAttributeMap[link][attr]] = this.model.get(attr);
                }, this);
                callback(attributes);
            });

            this.listenTo(this.model, 'updateRelationshipPanel', link => {
                this.updateRelationshipPanel(link);
            });

            if (this.getMetadata().get('scopes.' + this.scope + '.stream')) {
                if (this.model.has('isFollowed')) {
                    this.handleFollowButton();
                }

                this.listenTo(this.model, 'change:isFollowed', function () {
                    this.handleFollowButton();
                }, this);
            }

            var collection = this.collection = this.model.collection;
            if (collection) {
                this.listenTo(this.model, 'destroy', function () {
                    collection.remove(this.model.id);
                    collection.trigger('sync');
                }, this);

                if ('indexOfRecord' in this.options) {
                    this.indexOfRecord = this.options.indexOfRecord;
                } else {
                    this.indexOfRecord = collection.indexOf(this.model);
                }
            }

            if (this.navigationButtons) {
                const header = $('.header-items');

                if (header.length) {
                    let navigateButtonsEnabled = !this.navigateButtonsDisabled && !!this.model.collection;

                    if (navigateButtonsEnabled) {
                        this.navigationButtons.previous.disabled = true;
                        this.navigationButtons.next.disabled = true;

                        if (this.indexOfRecord > 0) {
                            this.navigationButtons.previous.disabled = false;
                        }

                        if (this.indexOfRecord < this.model.collection.total - 1) {
                            this.navigationButtons.next.disabled = false;
                        } else {
                            if (this.model.collection.total === -1) {
                                this.navigationButtons.next.disabled = false;
                            } else if (this.model.collection.total === -2) {
                                if (this.indexOfRecord < this.model.collection.length - 1) {
                                    this.navigationButtons.next.disabled = false;
                                }
                            }
                        }

                        if (this.navigationButtons.previous.disabled && this.navigationButtons.next.disabled) {
                            navigateButtonsEnabled = false;
                        }
                    }

                    if (navigateButtonsEnabled) {
                        for (const [key, data] of Object.entries(this.navigationButtons)) {
                            this.addMenuItem('buttons', {
                                name: key,
                                html: data.html,
                                style: data.disabled ? 'default disabled' : 'default',
                                action: key,
                                title: this.translate(data.title)
                            }, true)
                        }
                    }
                }
            }
        },

        switchToModelByIndex: function (indexOfRecord) {
            if (!this.model.collection) return;
            var model = this.model.collection.at(indexOfRecord);
            if (!model) {
                throw new Error("Model is not found in collection by index.");
            }
            var id = model.id;

            var scope = model.name || this.scope;

            let mode = 'view';
            if (this.mode === 'edit') {
                mode = 'edit';
            }

            this.getRouter().navigate('#' + scope + '/' + mode + '/' + id, {trigger: false});
            this.getRouter().dispatch(scope, mode, {
                id: id,
                model: model,
                indexOfRecord: indexOfRecord
            });
        },

        actionPrevious: function () {
            if (!this.model.collection) return;
            if (!(this.indexOfRecord > 0)) return;

            var indexOfRecord = this.indexOfRecord - 1;
            this.switchToModelByIndex(indexOfRecord);
        },

        actionNext: function () {
            if (!this.model.collection) return;
            if (!(this.indexOfRecord < this.model.collection.total - 1) && this.model.collection.total >= 0) return;
            if (this.model.collection.total === -2 && this.indexOfRecord >= this.model.collection.length - 1) {
                return;
            }

            var collection = this.model.collection;

            var indexOfRecord = this.indexOfRecord + 1;
            if (indexOfRecord <= collection.length - 1) {
                this.switchToModelByIndex(indexOfRecord);
            } else {
                var initialCount = collection.length;

                this.listenToOnce(collection, 'sync', function () {
                    var model = collection.at(indexOfRecord);
                    this.switchToModelByIndex(indexOfRecord);
                }, this);
                collection.fetch({
                    more: true,
                    remove: false,
                });
            }
        },

        afterRender() {
            $('.page-header').addClass('detail-page-header');

            Dep.prototype.afterRender.call(this);
        },

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > .header',
                scope: this.scope
            });

            this.listenTo(this.model, 'sync', function (model) {
                if (model.hasChanged('name')) {
                    if (this.getView('header')) {
                        this.getView('header').reRender();
                    }
                    this.updatePageTitle();
                }
            }, this);
        },

        getBoolFilterData(link) {
            let data = {};
            this.selectBoolFilterLists[link].forEach(item => {
                if (this.boolFilterData[link] && typeof this.boolFilterData[link][item] === 'function') {
                    data[item] = this.boolFilterData[link][item].call(this);
                }
            });
            return data;
        },

        actionSelectRelatedEntity(data) {
            let link = data.link;
            let massRelateDisabled = data.massRelateDisabled || false;
            let scope = data.scope || this.model.defs['links'][link].entity;
            let afterSelectCallback = data.afterSelectCallback;
            let boolFilterListCallback = data.boolFilterListCallback;
            let boolFilterDataCallback = data.boolFilterDataCallback;
            let panelView = this.getPanelView(link);

            let filters = Espo.Utils.cloneDeep(this.selectRelatedFilters[link]) || {};
            for (let filterName in filters) {
                if (typeof filters[filterName] == 'function') {
                    let filtersData = filters[filterName].call(this);
                    if (filtersData) {
                        filters[filterName] = filtersData;
                    } else {
                        delete filters[filterName];
                    }
                }
            }

            let primaryFilterName = data.primaryFilterName || this.selectPrimaryFilterNames[link] || null;
            if (typeof primaryFilterName == 'function') {
                primaryFilterName = primaryFilterName.call(this);
            }

            let boolFilterList = data.boolFilterList || Espo.Utils.cloneDeep(this.selectBoolFilterLists[link] || []);
            if (typeof boolFilterList == 'function') {
                boolFilterList = boolFilterList.call(this);
            }

            if (boolFilterListCallback && panelView && typeof panelView[boolFilterListCallback] === 'function') {
                boolFilterList = panelView[boolFilterListCallback]();
            }

            let boolfilterData = [];
            if (boolFilterDataCallback && panelView && typeof panelView[boolFilterDataCallback] === 'function') {
                boolfilterData = panelView[boolFilterDataCallback](boolFilterList);
            }

            let viewName =
                ((panelView || {}).defs || {}).modalSelectRecordView ||
                this.getMetadata().get(['clientDefs', scope, 'modalViews', 'select']) ||
                'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: true,
                createButton: false,
                listLayout:  data.listLayout,
                filters: filters,
                massRelateEnabled: false,
                primaryFilterName: primaryFilterName,
                boolFilterList: boolFilterList,
                boolFilterData: boolfilterData
            }, function (dialog) {
                dialog.render();
                this.notify(false);
                dialog.once('select', selectObj => {
                    if (massRelateDisabled && !Array.isArray(selectObj)) {
                        const list = dialog.getView('list');

                        if (list) {
                            selectObj = list.getSelected();
                        }
                    }

                    if (afterSelectCallback && panelView && typeof panelView[afterSelectCallback] === 'function') {
                        panelView[afterSelectCallback](selectObj);
                    } else {
                        let data = {};
                        if (Array.isArray(selectObj)) {
                            data.massRelate = true;
                            data.where = [{
                                type: 'in',
                                field: 'id',
                                value: selectObj.map(item => item.id)
                            }]
                        } else {
                            data = selectObj;
                        }

                        this.ajaxPostRequest(`${this.scope}/${this.model.id}/${link}`, data)
                            .then(() => {
                                this.notify('Linked', 'success');
                                this.updateRelationshipPanel(link);
                                this.model.trigger('after:relate', link);
                            });
                    }
                }, this);
            }.bind(this));
        },

        getPanelView(name) {
            let panelView;
            let recordView = this.getView('record');
            if (recordView) {
                let bottomView = recordView.getView('bottom');
                if (bottomView) {
                    panelView = bottomView.getView(name)
                }
            }
            return panelView;
        },

        addUnfollowButtonToMenu: function () {
            this.removeMenuItem('follow', true);

            this.addMenuItem('buttons', {
                name: 'unfollow',
                label: 'Followed',
                style: 'success',
                action: 'unfollow'
            }, true);
        },

        addFollowButtonToMenu: function () {
            this.removeMenuItem('unfollow', true);

            this.addMenuItem('buttons', {
                name: 'follow',
                label: 'Follow',
                style: 'default',
                html: '<span class="fas fa-arrow-right fa-sm"></span> ' + this.translate('Follow'),
                action: 'follow'
            }, true);
        },

        setupRecord: function () {
            var o = {
                model: this.model,
                el: '#main > .record',
                scope: this.scope
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.options.params && this.options.params.rootUrl) {
                o.rootUrl = this.options.params.rootUrl;
            }
            if (!this.navigateButtonsDisabled) {
                o.hasNext = !this.navigationButtons.next.disabled;
            }
            this.createView('record', this.getRecordViewName(), o);
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        handleFollowButton: function () {
            if (this.model.get('isFollowed')) {
                this.addUnfollowButtonToMenu();
            } else {
                if (this.getAcl().checkModel(this.model, 'stream')) {
                    this.addFollowButtonToMenu();
                }
            }
        },

        actionFollow: function () {
            $el = this.$el.find('[data-action="follow"]');
            $el.addClass('disabled');
            $.ajax({
                url: this.model.name + '/' + this.model.id + '/subscription',
                type: 'PUT',
                success: function () {
                    $el.remove();
                    this.model.set('isFollowed', true);
                }.bind(this),
                error: function () {
                    $el.removeClass('disabled');
                }.bind(this)
            });
        },

        actionUnfollow: function () {
            $el = this.$el.find('[data-action="unfollow"]');
            $el.addClass('disabled');
            $.ajax({
                url: this.model.name + '/' + this.model.id + '/subscription',
                type: 'DELETE',
                success: function () {
                    $el.remove();
                    this.model.set('isFollowed', false);
                }.bind(this),
                error: function () {
                    $el.removeClass('disabled');
                }.bind(this)
            });

        },

        getHeader: function () {
            let name = Handlebars.Utils.escapeExpression(this.model.get('name'));

            if (name === '') {
                name = this.model.id;
            }

            let rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;

            let headerIconHtml = this.getHeaderIconHtml();

            let path = [];
            path.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');

            if (this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy' && this.getMetadata().get(`scopes.${this.scope}.multiParents`) !== true && this.model.get('hierarchyRoute')) {
                $.each(this.model.get('hierarchyRoute'), (id, name) => {
                    path.push('<a href="' + rootUrl + '/view/' + id + '" class="action">' + name + '</a>');
                });
            }

            path.push(name);

            return this.buildHeaderHtml(path);
        },

        updatePageTitle: function () {
            this.setPageTitle(this.model.get('name'));
        },

        updateRelationshipPanel: function (name) {
            var bottom = this.getView('record').getView('bottom');
            if (bottom) {
                var rel = bottom.getView(name);
                if (rel) {
                    rel.collection.fetch();
                }
            }
        },

        actionSelectRelated: function (data) {
            var link = data.link;

            if (!this.model.defs['links'][link]) {
                throw new Error('Link ' + link + ' does not exist.');
            }
            var scope = this.model.defs['links'][link].entity;
            var foreign = this.model.defs['links'][link].foreign;

            var massRelateEnabled = this.getMetadata().get('clientDefs.' + this.scope + '.relationshipPanels.' + link + '.massRelateEnabled')
            if (massRelateEnabled === null && foreign) {
                var foreignType = this.getMetadata().get('entityDefs.' + scope + '.links.' + foreign + '.type');
                if (foreignType == 'hasMany') {
                    massRelateEnabled = true;
                }
            }

            var self = this;
            var attributes = {};

            var filters = Espo.Utils.cloneDeep(this.selectRelatedFilters[link]) || {};
            for (var filterName in filters) {
                if (typeof filters[filterName] == 'function') {
                    var filtersData = filters[filterName].call(this);
                    if (filtersData) {
                        filters[filterName] = filtersData;
                    } else {
                        delete filters[filterName];
                    }
                }
            }

            var primaryFilterName = data.primaryFilterName || this.selectPrimaryFilterNames[link] || null;
            if (typeof primaryFilterName == 'function') {
                primaryFilterName = primaryFilterName.call(this);
            }


            var dataBoolFilterList = data.boolFilterList;
            if (typeof data.boolFilterList == 'string') {
                dataBoolFilterList = data.boolFilterList.split(',');
            }

            dataBoolFilterList = dataBoolFilterList || [];
            $.each(dataBoolFilterList, function (key, name) {
                dataBoolFilterList[key] = name.replace('{{id}}', self.model.id);
            });

            var boolFilterList = dataBoolFilterList || Espo.Utils.cloneDeep(this.selectBoolFilterLists[link] || []);

            if (typeof boolFilterList == 'function') {
                boolFilterList = boolFilterList.call(this);
            }

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.select') || 'views/modals/select-records';

            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: this.model.defs['links'][link].type !== 'belongsTo',
                createButton: false,
                filters: filters,
                massRelateEnabled: massRelateEnabled,
                primaryFilterName: primaryFilterName,
                boolFilterList: boolFilterList
            }, function (dialog) {
                dialog.render();
                this.notify(false);
                dialog.once('select', function (selectObj) {
                    var data = {};
                    if (Object.prototype.toString.call(selectObj) === '[object Array]') {
                        var ids = [];
                        selectObj.forEach(function (model) {
                            ids.push(model.id);
                        });
                        data.ids = ids;
                    } else {
                        if (selectObj.massRelate) {
                            data.massRelate = true;
                            data.where = selectObj.where;
                        } else {
                            data.id = selectObj.id;
                        }
                    }

                    const selectConfirm = this.getMetadata().get(`clientDefs.${self.scope}.relationshipPanels.${link}.selectConfirm`) || false;
                    if (selectConfirm) {
                        let parts = selectConfirm.split('.');
                        Espo.Ui.confirm(this.translate(parts[2], parts[1], parts[0]), {
                            confirmText: self.translate('Apply'),
                            cancelText: self.translate('Cancel')
                        }, () => {
                            this.createLink(this.scope, this.model.id, link, data);
                        });
                    } else {
                        this.createLink(this.scope, this.model.id, link, data);
                    }
                }.bind(this));
            }.bind(this));
        },

        createLink: function (scope, id, link, data) {
            $.ajax({
                url: scope + '/' + id + '/' + link,
                type: 'POST',
                data: JSON.stringify(data),
                success: function () {
                    this.notify('Linked', 'success');
                    this.updateRelationshipPanel(link);
                    this.model.trigger('after:relate', link);
                }.bind(this),
                error: function () {
                    this.notify('Error occurred', 'error');
                }.bind(this)
            });
        },

        actionDuplicate: function () {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest(this.scope + '/action/getDuplicateAttributes', {
                id: this.model.id
            }).then(function (attributes) {
                Espo.Ui.notify(false);
                var url = '#' + this.scope + '/create';

                this.getRouter().dispatch(this.scope, 'create', {
                    attributes: attributes,
                });
                this.getRouter().navigate(url, {trigger: false});
            }.bind(this));


        },

    });
});

