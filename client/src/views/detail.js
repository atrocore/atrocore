/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/detail', ['views/main', 'lib!JsTree'], function (Dep) {

    return Dep.extend({

        template: 'detail',

        scope: null,

        name: 'Detail',

        optionsToPass: ['attributes', 'returnUrl', 'returnDispatchParams', 'rootUrl'],

        recordView: 'views/record/detail',

        rightSideView: 'views/record/right-side-view',

        relatedAttributeMap: {},

        relatedAttributeFunctions: {},

        selectRelatedFilters: {},

        selectPrimaryFilterNames: {},

        selectBoolFilterLists: {},

        mandatorySelectAttributeLists: {},

        boolFilterData: {},

        navigateButtonsDisabled: false,

        hasPrevious: false,

        hasNext: false,

        treeAllowed: false,

        mode: 'detail',

        data: function () {
            return {
                treeAllowed: this.treeAllowed,
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.recordView = this.options.recordView || this.recordView;
            this.navigateButtonsDisabled = this.options.navigateButtonsDisabled || this.navigateButtonsDisabled;

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


            if (!this.getMetadata().get('scopes.' + this.scope + '.streamDisabled')) {
                this.handleFollowButton(!this.model.has('isFollowed'));

                this.listenTo(this.model, 'change:isFollowed', function () {
                    this.handleFollowButton();
                }, this);

                this.listenTo(this.model, 'change:followersNames', function () {
                    window.dispatchEvent(new CustomEvent('record:followers-updated', { detail: this.model.get('followersNames') }));
                });
            }

            if (!this.getMetadata().get('scopes.' + this.scope + '.bookmarkDisabled')) {
                let data = {};
                if (this.model.get('bookmarkId')) {
                    if (this.getAcl().check('Bookmark', 'delete')) {
                        data = {
                            name: 'bookmarking',
                            action: 'unbookmark',
                        }
                    }

                } else {
                    if (this.getAcl().check('Bookmark', 'create')) {
                        data = {
                            name: 'bookmarking',
                            action: 'bookmark',
                        }
                    }
                }

                this.addMenuItem('buttons', data, true, false, true);
            }

            if (this.model && !this.model.isNew() && this.getMetadata().get(['scopes', this.scope, 'object'])
                && this.getMetadata().get(['scopes', this.scope, 'overviewFilters']) !== false
                && this.getMetadata().get(['scopes', this.scope, 'hideFieldTypeFilters']) !== true
            ) {
                this.addMenuItem('buttons', {
                    name: 'filtering',
                    action: 'applyOverviewFilter'
                }, true, false, true);
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

            if (!this.navigateButtonsDisabled && !!this.model.collection) {
                this.hasPrevious = false;
                this.hasNext = false;

                if (this.indexOfRecord > 0) {
                    this.hasPrevious = true;
                }

                if (this.indexOfRecord < this.model.collection.total - 1) {
                    this.hasNext = true;
                } else {
                    if (this.model.collection.total === -1) {
                        this.hasNext = true;
                    } else if (this.model.collection.total === -2) {
                        if (this.indexOfRecord < this.model.collection.length - 1) {
                            this.hasNext = true;
                        }
                    }
                }
            }

            this.addMenuItem('buttons', {
                action: 'navigation'
            });

            this.listenTo(this.model, 'after:change-mode', (mode) => {
                this.mode = mode;
                $('#main main').attr('data-mode', mode);
                window.dispatchEvent(new CustomEvent('record-mode:changed', { detail: mode }));
            });
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

            this.getRouter().navigate('#' + scope + '/' + mode + '/' + id, { trigger: false });
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
            const main = $('#main main');
            const header = $('.page-header');

            header.addClass('detail-page-header');
            Dep.prototype.afterRender.call(this);

            main.attr('data-mode', this.mode);

            this.setupHeader();

            const view = this.getView('record');
            if (this.treeAllowed) {
                window.treePanelComponent = new Svelte.TreePanel({
                    target: $(`${this.options.el} .content-wrapper`).get(0),
                    anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                    props: {
                        scope: this.scope,
                        model: this.model,
                        mode: 'detail',
                        callbacks: {
                            selectNode: data => {
                                view.selectNode(data);
                            },
                            treeLoad: (treeScope, treeData) => {
                                if (view.treeLoad) {
                                    view.treeLoad(treeScope, treeData);
                                }
                            },
                            treeReset: () => {
                                view.treeReset()
                            },
                            treeWidthChanged: (width) => {
                                view.onTreeResize(width)
                            }
                        },
                        renderLayoutEditor: (container) => {
                            if (this.getUser().isAdmin()) {
                                this.createView('treeLayoutConfigurator', "views/record/layout-configurator", {
                                    scope: this.scope,
                                    viewType: 'navigation',
                                    layoutData: window.treePanelComponent.getLayoutData(),
                                    el: container,
                                }, (view) => {
                                    view.on("refresh", () => {
                                        window.treePanelComponent.refreshLayout()
                                    })
                                    view.render()
                                })
                            }
                        }
                    }
                });

                view.onTreePanelRendered();
            }

            this.setupRightSideView();

            let isScrolled = false;

            main.off('scroll.breadcrumbs');
            main.on('scroll.breadcrumbs', (e) => {
                if (window.screen.width < 768) {
                    return;
                }

                if (e.currentTarget.scrollTop > 0) {
                    if (!isScrolled) {
                        isScrolled = true;
                        setTimeout(() => requestAnimationFrame(() => {
                            main.css('padding-bottom', header.find('.header-breadcrumbs').outerHeight() || 0);
                            window.dispatchEvent(new CustomEvent('breadcrumbs:header-updated', { detail: !isScrolled }));
                        }), 100);
                    }
                } else {
                    if (isScrolled) {
                        isScrolled = false;
                        setTimeout(() => requestAnimationFrame(() => {
                            main.css('padding-bottom', '');
                            window.dispatchEvent(new CustomEvent('breadcrumbs:header-updated', { detail: !isScrolled }));
                        }), 100);
                    }
                }
            });
        },

        executeAction(action, data, event) {
            var method = 'action' + Espo.Utils.upperCaseFirst(action);
            if (typeof this[method] == 'function') {
                this[method].call(this, data, event);
                event.stopPropagation();
                return;
            }

            const record = this.getView('record');
            record.executeAction(action, data, event);
        },

        getVisiblePanels() {
            return (this.panelsList ?? []).filter(panel => {
                if (panel.name === 'panel-0') {
                    return true;
                }

                if (this.isPanelClosed(panel)) {
                    return true;
                }

                const panelElement = document.querySelector(`div.panel[data-name="${panel.name}"]`);

                return panelElement && panelElement.style.display !== 'none' && !$(panelElement).hasClass('hidden');
            });
        },

        isPanelClosed(panel) {
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? []
            return (scopePreferences[panel.isAttributePanel ? 'closedAttributePanels' : 'closed'] || []).includes(panel.name)
        },

        scrollToPanel(name) {
            let panel = $('#main').find(`.panel[data-name="${name}"]`);
            if (panel.size() > 0) {
                const header = document.querySelector('.page-header');
                const content = document.querySelector("main") || document.querySelector('#main');
                panel = panel.get(0);

                if (!content || !panel) return;

                const panelOffset = panel.getBoundingClientRect().top + content.scrollTop - content.getBoundingClientRect().top;
                const stickyOffset = header.offsetHeight;
                content.scrollTo({
                    top: window.screen.width < 768 ? panelOffset : panelOffset - stickyOffset,
                    behavior: "smooth"
                });
            }
        },

        setupLayoutEditorButton() {
            let el = this.options.el || '#' + (this.id);
            const recordView = this.getView('record');
            if (!recordView) {
                return;
            }
            const bottomView = recordView.getView('bottom');

            this.createView('layoutRelationshipsConfigurator', "views/record/layout-configurator", {
                scope: this.scope,
                viewType: 'relationships',
                layoutData: bottomView.layoutData,
                el: el + ' .panel-navigation .layout-editor-container',
                alignRight: true,
            }, (view) => {
                view.on("refresh", () => {
                    recordView.createBottomView(view => {
                        view.render();
                    })
                })
                view.render();
            })
        },

        initHeaderObserver() {
            return new MutationObserver(mutations => {
                mutations.forEach(mutation => {
                    mutation.addedNodes.forEach(node => {
                        if (!node instanceof HTMLElement || !node.classList) {
                            return;
                        }

                        if (node.classList.contains('layout-editor-container')) {
                            this.setupLayoutEditorButton();
                        }
                    })
                });
            })
        },

        getHeaderOptions() {
            let observer = null;

            const record = this.getView('record');

            const hasLayoutEditor = this.getMetadata().get(['scopes', this.model.name, 'layouts']) && this.getAcl().check('LayoutProfile', 'read');
            const recordButtons = Object.assign(record?.getRecordButtons() || {}, {
                headerButtons: this.getMenu(),
                isOverviewFilterActive: this.isOverviewFilterApply(),
                followers: this.model.get('followersNames') ?? {},
                hasPrevious: this.hasPrevious,
                hasNext: this.hasNext,
                hasLayoutEditor: hasLayoutEditor,
                recordId: this.model.get('id') || null,
                executeAction: (action, data, event) => {
                    this.executeAction(action, data, event);
                },
            });

            return {
                params: {
                    mode: this.mode,
                    scope: this.scope,
                    id: this.model.id,
                    permissions: {
                        canRead: this.getAcl().check(this.scope, 'read'),
                        canEdit: this.getAcl().check(this.scope, 'edit'),
                        canCreate: this.getAcl().check(this.scope, 'create'),
                        canDelete: this.getAcl().check(this.scope, 'delete'),
                        canReadStream: this.getAcl().check(this.scope, 'stream'),
                    },
                    breadcrumbs: this.getBreadcrumbsItems(),
                    afterOnMount: () => {
                        if (hasLayoutEditor) {
                            this.setupLayoutEditorButton();
                        }

                        observer = this.initHeaderObserver();
                        if (observer) {
                            observer.observe(document.querySelector('.page-header'), {
                                childList: true,
                                subtree: true
                            });
                        }
                    },
                    afterOnDestroy: () => {
                        if (observer) {
                            observer.disconnect();
                        }
                    }
                },
                recordButtons: recordButtons,
                callbacks: {
                    onFollow: () => {
                        let followersNames = this.model.get('followersNames') || {};
                        followersNames[this.getUser().get('id')] = this.getUser().get('name');
                        this.model.set('isFollowed', true);
                        this.model.set('followersIds', Object.keys(followersNames));
                        this.model.set('followersNames', followersNames);
                    },
                    onUnfollow: () => {
                        let followersNames = Object.fromEntries(Object.entries(this.model.get('followersNames') || {}).filter(([key]) => key !== this.getUser().get('id')));
                        this.model.set('isFollowed', false);
                        this.model.set('followersIds', Object.keys(followersNames));
                        this.model.set('followersNames', followersNames);
                    },
                },
                anchorNavItems: this.getVisiblePanels(),
                anchorScrollCallback: (name, event) => {
                    const panel = this.panelsList.filter(p => p.name === name)[0];
                    if (this.isPanelClosed(panel)) {
                        if (panel.isAttributePanel) {
                            const recordView = this.getView('record')

                            recordView.showAttributeValuePanel(name, () => {
                                setTimeout(() => this.scrollToPanel(panel.name), 100);
                            })
                        } else {
                            Backbone.trigger('create-bottom-panel', panel);
                            this.listenToOnce(Backbone, 'after:create-bottom-panel', function (panel) {
                                setTimeout(() => this.scrollToPanel(panel.name), 100);
                            })
                        }
                    } else {
                        this.scrollToPanel(name);
                    }
                }
            };
        },

        setupHeader: function () {
            this.svelteDetailHeader = new Svelte.DetailHeader({
                target: document.querySelector('#main main > .header'),
                props: this.getHeaderOptions()
            });

            this.listenTo(this.model, 'sync after:save after:inlineEditSave', function () {
                window.dispatchEvent(new CustomEvent('breadcrumbs:items-updated', { detail: this.getBreadcrumbsItems() }));
                this.updatePageTitle();
            });
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
            let foreign = this.model.defs['links'][link]?.foreign;
            let type = this.model.defs['links'][link]?.type;
            let selectDuplicateEnabled = false;

            if (foreign) {
                var foreignType = this.getMetadata().get('entityDefs.' + scope + '.links.' + foreign + '.type');

                if (type === 'hasMany' && foreignType === 'belongsTo') {
                    selectDuplicateEnabled = true;
                }
            }

            let filters;

            if (typeof this.selectRelatedFilters[link] == 'function') {
                filters = this.selectRelatedFilters[link].call(this) || {}
            } else {
                filters = Espo.Utils.cloneDeep(this.selectRelatedFilters[link]) || {};
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
                multiple: data.multiple ?? true,
                createButton: false,
                listLayout: data.listLayout,
                filters: filters,
                massRelateEnabled: false,
                primaryFilterName: primaryFilterName,
                boolFilterList: boolFilterList,
                boolFilterData: boolfilterData,
                selectDuplicateEnabled: selectDuplicateEnabled
            }, function (dialog) {
                dialog.render();
                this.notify(false);
                dialog.once('select', (selectObj, duplicate) => {

                    if (massRelateDisabled && !Array.isArray(selectObj)) {
                        const list = dialog.getView('list');

                        if (list) {
                            selectObj = list.getSelected();
                        }
                    }

                    if (afterSelectCallback && panelView && typeof panelView[afterSelectCallback] === 'function') {
                        panelView[afterSelectCallback](selectObj);
                    } else {
                        let data = { shouldDuplicateForeign: duplicate };
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
                            .then((resp) => {
                                if (resp) {
                                    this.notify(this.translate(data.shouldDuplicateForeign ? 'duplicatedAndLinked' : 'linked', 'messages'), 'success');
                                } else {
                                    this.notify(this.translate('linkJobsCreated', 'messages'), 'success');
                                }
                                this.updateRelationshipPanel(link);

                                if (this.mode !== 'edit') {
                                    this.model.trigger('after:relate', link);
                                }
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
            this.addMenuItem('buttons', {
                name: 'following',
                action: 'unfollow',
            }, true, false, true);
        },

        addFollowButtonToMenu: function () {
            this.addMenuItem('buttons', {
                name: 'following',
                action: 'follow',
            }, true, false, true);
        },

        isTreeAllowed() {
            return !this.getMetadata().get(['scopes', this.scope, 'leftSidebarDisabled'])
        },

        setupRecord: function () {
            const o = {
                model: this.model,
                el: '#main main > .record',
                scope: this.scope
            };
            this.optionsToPass.forEach(function (option) {
                o[option] = this.options[option];
            }, this);
            if (this.options.params && this.options.params.rootUrl) {
                o.rootUrl = this.options.params.rootUrl;
            }
            if (!this.navigateButtonsDisabled) {
                o.hasNext = this.hasNext;
            }

            this.treeAllowed = !o.isWide && this.isTreeAllowed();

            this.createView('record', this.getRecordViewName(), o, view => {
                this.listenTo(view, 'detailPanelsLoaded', data => {
                    this.panelsList = data.list;
                    window.dispatchEvent(new CustomEvent('detail:panels-loaded', { detail: this.getVisiblePanels() }));
                });

                this.listenTo(view.model, 'change', () => {
                    window.dispatchEvent(new CustomEvent('detail:panels-loaded', { detail: this.getVisiblePanels() }));
                });

                this.listenTo(view, 'after:render', view => {
                    window.dispatchEvent(new CustomEvent('detail:panels-loaded', { detail: this.getVisiblePanels() }));
                });
            });
        },

        getRecordViewName: function () {
            return this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.detail') || this.recordView;
        },

        handleFollowButton: function (shouldBeHidden = false) {
            if (shouldBeHidden) {
                this.addMenuItem('buttons', {
                    name: 'following',
                    hidden: true
                }, true, false, true);
            } else if (this.model.get('isFollowed')) {
                this.addUnfollowButtonToMenu();
            } else {
                if (this.getAcl().checkModel(this.model, 'stream')) {
                    this.addFollowButtonToMenu();
                }
            }
        },

        getOverviewFiltersList: function () {
            if (this.overviewFilterList) {
                return this.overviewFilterList;
            }
            let result = [
                {
                    name: "fieldFilter",
                    label: this.translate('fieldStatus'),
                    options: ["allValues", "filled", "empty", "optional", "required"],
                    selfExcludedFieldsMap: {
                        filled: 'empty',
                        empty: 'filled',
                        optional: 'required',
                        required: 'optional'
                    },
                    defaultValue: 'allValues'
                }
            ];

            return this.overviewFilterList = result;
        },

        isOverviewFilterApply() {
            for (const filter of this.getOverviewFiltersList()) {
                let selected = this.getStorage().get(filter.name, this.scope) ?? [];
                if (!Array.isArray(selected) || !selected.length) {
                    continue;
                }
                if (selected && selected.join('') !== filter.defaultValue) {
                    return true;
                }
            }

            return false;
        },

        actionApplyOverviewFilter: function (e) {
            this.model.trigger('overview-filters-changed')
        },

        getBreadcrumbsItems: function (isAdmin = false) {
            const result = Dep.prototype.getBreadcrumbsItems.call(this, isAdmin) || [];
            const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;

            result.push({
                url: rootUrl,
                label: this.getLanguage().translate(this.scope, 'scopeNamesPlural')
            });

            if (this.isHierarchical() && this.getMetadata().get(`scopes.${this.scope}.multiParents`) !== true) {
                (this.model.get('routesNames')?.[0] || []).forEach(item => {
                    result.push({
                        url: `${rootUrl}/view/${item.id}`,
                        label: item.name
                    });
                })
            }

            result.push({
                url: `${rootUrl}/view/${this.model.id}`,
                label: this.getLabel(),
                className: 'header-title'
            })

            return result;
        },

        getLabel() {
            if (this.model.get('nameLabel')) {
                return this.model.get('nameLabel')
            }

            if (this.getMetadata().get(`entityDefs.${this.scope}.fields.name.isMultilang`) === true) {
                const [field, userLocaleCode] = this.getLocalizedFieldData(this.scope, 'name')

                if (userLocaleCode) {
                    return this.model.get(field) || this.translate('None', 'labels', 'Global')
                }
            }

            return this.model.get('name') || this.model.id
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy';
        },

        updatePageTitle: function () {
            this.setPageTitle(this.model.get('name') ?? this.model.id);
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
            let type = this.model.defs['links'][link].type;
            let panelView = this.getPanelView(link);
            let boolFilterListCallback = data.boolFilterListCallback;
            let boolFilterDataCallback = data.boolFilterDataCallback;

            var massRelateEnabled = this.getMetadata().get('clientDefs.' + this.scope + '.relationshipPanels.' + link + '.massRelateEnabled')
            let selectDuplicateEnabled = false;
            if (massRelateEnabled === null && foreign) {
                var foreignType = this.getMetadata().get('entityDefs.' + scope + '.links.' + foreign + '.type');
                if (foreignType == 'hasMany') {
                    massRelateEnabled = true;
                }
                if (type === 'hasMany' && foreignType === 'belongsTo') {
                    selectDuplicateEnabled = true;
                }
            }

            var self = this;
            var attributes = {};
            let filters;

            if (typeof this.selectRelatedFilters[link] == 'function') {
                filters = this.selectRelatedFilters[link].call(this) || {}
            } else {
                filters = Espo.Utils.cloneDeep(this.selectRelatedFilters[link]) || {};
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

            if (boolFilterListCallback && panelView && typeof panelView[boolFilterListCallback] === 'function') {
                boolFilterList = _.extend(boolFilterList, panelView[boolFilterListCallback]());
            }
            let boolFilterData = {}
            if (boolFilterDataCallback && panelView && typeof panelView[boolFilterDataCallback] === 'function') {
                boolFilterData = panelView[boolFilterDataCallback](boolFilterList);
            }

            let mandatorySelectAttributeList = Espo.Utils.cloneDeep(this.mandatorySelectAttributeLists[link] || []);


            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.select') || 'views/modals/select-records';
            this.notify('Loading...');
            this.createView('dialog', viewName, {
                scope: scope,
                multiple: type !== 'belongsTo',
                createButton: false,
                allowSelectAllResult: !this.getMetadata().get(`clientDefs.${this.model.name}.relationshipPanels.${link}.disabledSelectAllResult`),
                filters: filters,
                massRelateEnabled: massRelateEnabled,
                primaryFilterName: primaryFilterName,
                boolFilterList: boolFilterList,
                boolFilterData: boolFilterData,
                selectDuplicateEnabled: selectDuplicateEnabled,
                mandatorySelectAttributeList: mandatorySelectAttributeList
            }, function (dialog) {
                dialog.render();
                this.notify(false);
                dialog.once('select', function (selectObj, duplicate = false) {
                    var data = { shouldDuplicateForeign: duplicate };
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

                    const method = 'selectConfirm' + Espo.utils.upperCaseFirst(link)
                    let execute = true
                    if (typeof this[method] === 'function') {
                        execute = this[method](selectObj, duplicate)
                    }
                    const selectConfirm = this.getMetadata().get(`clientDefs.${self.scope}.relationshipPanels.${link}.selectConfirm`) || false;
                    if (selectConfirm && execute) {
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
                    this.notify(data.shouldDuplicateForeign ? this.translate('duplicatedAndLinked', 'messages') : 'Linked', 'success');
                    this.updateRelationshipPanel(link);
                    if (this.mode !== 'edit') {
                        this.model.trigger('after:relate', link);
                    }
                }.bind(this),
                error: function () {
                    this.updateRelationshipPanel(link);
                    if (this.mode !== 'edit') {
                        this.model.trigger('after:relate', link);
                    }
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
                this.getRouter().navigate(url, { trigger: false });
            }.bind(this));
        },

        remove(dontEmpty) {
            Dep.prototype.remove.call(this, dontEmpty);
            if (this.svelteDetailHeader) {
                try {
                    this.svelteDetailHeader.$destroy();
                } catch (e) {
                }
                this.svelteDetailHeader = null;
            }
        }
    });
});

