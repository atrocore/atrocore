/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/panels/records-in-groups', ['views/record/panels/relationship', 'views/record/panels/bottom'],
    (Dep, BottomPanel) => Dep.extend({

        template: 'record/panels/records-in-groups',

        editableFields: ['value'],

        listInlineEditModeEnabled: false,

        data() {
            return _.extend({
                groups: this.groups,
                groupScope: this.groupScope,
                loadingGroups: this.loadingGroups
            }, Dep.prototype.data.call(this));
        },

        getCreateLink() {
            return this.link;
        },

        setup() {
            let bottomPanel = new BottomPanel();
            bottomPanel.setup.call(this);

            this.link = this.link || this.defs.link || this.panelName;

            if (!this.scope && !(this.link in this.model.defs.links)) {
                throw new Error('Link \'' + this.link + '\' is not defined in model \'' + this.model.name + '\'');
            }
            this.title = this.title || this.translate(this.link, 'links', this.model.name);
            this.scope = this.scope || this.model.defs.links[this.link].entity;

            if ('listInlineEditModeEnabled' in this.options) {
                this.listInlineEditModeEnabled = this.options.listInlineEditModeEnabled;
            }

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

            this.filterList = this.defs.filterList || this.filterList || null;

            if (this.filterList && this.filterList.length) {
                this.filter = this.getStoredFilter();
            }

            if (!this.createDisabled && this.checkAclAction('create')) {
                this.buttonList.push({
                    title: 'Create',
                    action: this.defs.createAction || 'createRelated',
                    link: this.getCreateLink(),
                    acl: 'create',
                    aclScope: this.scope,
                    html: '<i class="ph ph-plus"></i>',
                    data: {
                        link: this.getCreateLink(),
                        tabId: this.defs.tabId
                    }
                });
            }

            if (!this.disableSelect && this.checkAclAction('create')) {
                var data = { link: this.defs.name };
                if (this.defs.selectPrimaryFilterName) {
                    data.primaryFilterName = this.defs.selectPrimaryFilterName;
                }
                if (this.defs.selectBoolFilterList) {
                    data.boolFilterList = this.defs.selectBoolFilterList;
                }
                data.boolFilterListCallback = 'getSelectBoolFilterList';
                data.boolFilterDataCallback = 'getSelectBoolFilterData';
                data.afterSelectCallback = this.afterSelectCallback;
                data.scope = this.selectScope;

                this.actionList.unshift({
                    label: 'Select',
                    action: this.defs.selectAction || 'selectRelated',
                    data: data,
                    acl: 'edit',
                    aclScope: this.model.name
                });

                if (this.getAcl().check(this.groupScope, 'read')) {
                    this.actionList.push({
                        label: this.selectLabel || 'selectGroup',
                        action: 'selectGroup'
                    });
                }
            }

            if (!this.disableDeleteAll && this.checkAclAction('delete')) {
                this.actionList.push({
                    label: 'deleteAll',
                    action: 'deleteAllRelationshipEntities',
                    data: {
                        "relationshipScope": this.scope
                    },
                    acl: 'delete',
                    aclScope: this.scope
                });
            }

            this.setupActions();

            var layoutName = 'list';
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

            this.layoutName = layoutName;
            this.listLayout = listLayout;

            this.wait(true);
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;

                this.setFilter(this.filter);

                this.listenTo(this.model, 'change:classificationId update-all after:relate after:unrelate', link => {
                    if (!link || this.getLinksForRefresh().includes(link)) {
                        this.getCollectionFactory().create(this.scope, collection => {
                            this.collection = collection;
                            this.actionRefresh();
                        });
                    }
                });

                this.listenTo(this.model, 'overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });

                this.loadingGroups = true
                this.once('after:render', () => {
                    this.fetchCollectionGroups(() => {
                        this.loadingGroups = false
                        this.reRender()
                    })
                })

                this.wait(false);
            });

            this.addToLanguageObservables();
            this.listenTo(this, 'change:disabled-languages', () => {
                this.actionRefresh()
            })

            this.editableFields = ['value'];
        },
        afterRender() {
            Dep.prototype.afterRender.call(this);
            Dep.prototype.setupTotal.call(this)

            this.buildGroups();

            if (this.mode === 'edit') {
                this.setEditMode();
            }
        },

        getLinksForRefresh() {
            return [this.link, this.getCreateLink()]
        },

        fetchCollectionGroups(callback) {
            this.groups = []
            callback()
        },

        getSelectFields() {
            return this.baseSelectFields || [];
        },

        initGroupCollection(group, groupCollection, callback) {
            groupCollection.fetch().success(() => {
                groupCollection.forEach(item => {
                    if (this.collection.get(item.get('id'))) {
                        this.collection.remove(item.get('id'));
                    }
                    this.collection.add(item);
                });
                callback();
            });
        },

        getAdditionalListOptions() {
            return {}
        },

        buildGroups() {
            this.collection.reset()
            if (!this.groups || this.groups.length < 1) {
                this.afterGroupRender()
                return;
            }
            let count = 0;
            this.groups.forEach(group => {
                this.getCollectionFactory().create(this.scope, groupCollection => {
                    if (this.defs.sortBy) {
                        groupCollection.sortBy = this.defs.sortBy;
                    }
                    if (this.defs.asc) {
                        groupCollection.asc = this.defs.asc;
                    }
                    this.initGroupCollection(group, groupCollection, () => {
                        let viewName = this.defs.recordListView || 'views/record/list-in-groups';
                        let options = {
                            collection: groupCollection,
                            layoutName: this.layoutName,
                            listLayout: this.listLayout,
                            layoutRelatedScope: this.model.name + '.' + this.link,
                            checkboxes: false,
                            rowActionsView: this.defs.readOnly ? false : (this.defs.rowActionsView || this.rowActionsView),
                            buttonsDisabled: true,
                            el: `${this.options.el} .group[data-name="${group.key}"] .list-container`,
                            showMore: false,
                            groupId: group.id,
                            groupName: group.label,
                            groupScope: this.groupScope,
                            hierarchyEnabled: this.hierarchyEnabled,
                            disableSorting: true,
                            disableRefreshLayout: true,
                            disableRefreshOnLanguageChange: true,
                            listInlineEditModeEnabled: this.listInlineEditModeEnabled,
                            ...this.getAdditionalListOptions()
                        };

                        this.createView(group.key, viewName, options, view => {
                            view.listenTo(view, 'remove-group', (data) => this.unlinkGroup(data));
                            view.listenTo(view, 'remove-group-hierarchically', (data) => this.unlinkGroupHierarchy(data));

                            view.render(() => {
                                count++;
                                if (typeof view.getEditableFields === 'function') {
                                    this.editableFields = view.getEditableFields() ?? ['value']
                                }
                                if (count === this.groups.length) {
                                    this.afterGroupRender()
                                    this.trigger('groups-rendered');
                                }

                                view.on('refresh-layout', () => {
                                    this.actionRefresh()
                                })
                            });
                        });
                    });
                });
            });
        },

        afterGroupRender() {
            this.collection.total = this.collection.length
            this.collection.trigger('update-total', this.collection)
        },

        applyOverviewFilters() {
            this.getCollectionFactory().create(this.scope, collection => {
                this.collection = collection;
                this.actionRefresh();
            });
        },

        isEmptyValue(value) {
            return value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        isRequiredValue(view) {
            return view.model.get('isRequired') || false
        },


        getValueFields() {
            let fields = {};
            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView) {
                    groupView.rowList.forEach(row => {
                        let rowView = groupView.getView(row);
                        if (rowView) {
                            let containerView = rowView.getView('valueField');
                            if (containerView) {
                                let fieldView = containerView.getView('valueField');
                                if (fieldView) {
                                    fieldView.groupKey = group.key;
                                    fields[row] = fieldView;
                                }
                            }
                        }
                    });
                }
            });
            return fields;
        },

        controlRowVisibility(fieldView, rowId, hide) {
            let groupView = this.getView(fieldView.groupKey);
            let rowView = groupView.getView(rowId);
            if (hide) {
                rowView.$el.addClass('hidden');
            } else {
                rowView.$el.removeClass('hidden');
            }
            this.controlGroupVisibility(groupView);
        },

        controlGroupVisibility(groupView) {
            if (groupView.$el.find('.list-row.hidden').size() === (groupView.rowList || []).length) {
                groupView.$el.parent().addClass('hidden');
            } else {
                groupView.$el.parent().removeClass('hidden');
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

        actionRefresh() {
            this.fetchCollectionGroups(() => {
                this.reRender();
            });
        },
        setListMode() {
            this.mode = 'list';

            this.groups.forEach(group => {
                let groupView = this.getView(group.key);
                if (groupView && typeof groupView.setListMode === 'function') {
                    groupView.setListMode();
                }
            });

            this.reRender();
        },

        unlinkGroup(data) {

        },

        actionDeleteAllRelationshipEntities(data) {

        },

        getEditableFields() {
            return this.editableFields;
        },

        checkAclAction(action) {
            return this.getAcl().check(this.scope, action);
        },


        regulateTableSizes() {
            let thAndMawWidths = [];
            this.groups.forEach(group => {
                let $baseSelector = `.group[data-name="${group.key}"] .list-container table:not(".hidden")`;
                this.$el.find($baseSelector).find('th')
                    .each(function () {
                        let index = $(this).index();
                        let width = $(this).width();

                        if (!thAndMawWidths[index]) {
                            thAndMawWidths[index] = {
                                elements: [$(this)],
                                maxWidth: width
                            }
                        } else {
                            if (thAndMawWidths[index].maxWidth < width) {
                                thAndMawWidths[index].maxWidth = width;
                            }
                            thAndMawWidths[index].elements.push($(this));
                        }
                    });
            });
            thAndMawWidths.forEach((el, key) => {
                el.elements.forEach(th => {
                    th.css('width', el.maxWidth + 'px')
                });
            });

            this.groups.forEach(group => {
                const groupView = this.getView(group.key)
                if (groupView && typeof groupView.fullTableScroll === 'function') {
                    groupView.fullTableScroll()
                }
            })
        }
    })
);
