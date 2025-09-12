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

Espo.define('views/record/list', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/list', /**
         * @param {String} Type of the list. Can be 'list'.
         */
        type: 'list',

        name: 'list',

        presentationType: 'table', /**
         * @param {Bool} If true checkboxes will be shown.
         */
        checkboxes: true, /**
         * @param {Bool} If true clicking on the record link will trigger 'select' event with model passed.
         */
        selectable: false,

        rowActionsView: 'views/record/row-actions/default',

        rowActionsDisabled: false,

        scope: null,

        _internalLayoutType: 'list-row',

        listContainerEl: '.list > table > tbody',

        showCount: true,

        rowActionsColumnWidth: 25,

        buttonList: [],

        headerDisabled: false,

        massActionsDisabled: false,

        enabledFixedHeader: false,

        checkedAll: false,

        dragndropEventName: null,

        massRelationView: 'views/modals/select-entity-and-records',

        baseWidth: [],

        layoutData: null,

        layoutProfileId: null,

        searchManager: null,

        showFilter: false,

        showSearch: false,

        uniqueKey: 'default',

        events: {
            'click a.link': function (e) {
                e.stopPropagation();
                if (e.ctrlKey || !this.scope || this.selectable) {
                    return;
                }
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                var model = this.collection.get(id);

                var scope = this.getModelScope(id);

                var options = {
                    id: id,
                    model: model
                };
                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }

                this.getRouter().navigate('#' + scope + '/view/' + id, { trigger: false });
                this.getRouter().dispatch(scope, 'view', options);
            },
            'click [data-action="showMore"]': function () {
                this.showMoreRecords();
            },
            'click a.sort': function (e) {
                var field = $(e.currentTarget).data('name');
                this.toggleSort(field);
            },
            'click .pagination a': function (e) {
                var page = $(e.currentTarget).data('page');
                if ($(e.currentTarget).parent().hasClass('disabled')) {
                    return;
                }
                this.notify('Please wait...');
                this.collection.once('sync', function () {
                    this.notify(false);
                }.bind(this));

                if (page == 'current') {
                    this.collection.fetch();
                } else {
                    this.collection[page + 'Page'].call(this.collection);
                    this.trigger('paginate');
                }
                this.deactivate();
            },
            'click .record-checkbox': function (e) {
                const $target = $(e.currentTarget);
                const id = $target.data('id');

                if ($target.prop('checked')) {
                    this.checkRecord(id, $target);
                    this.checkIntervalRecords(e, $target);
                } else {
                    this.uncheckRecord(id, $target);
                }
            },
            'click .select-all': function (e) {
                if (e.shiftKey) {
                    let checked = $(e.currentTarget).prop('checked');

                    if (this.allResultIsChecked) {
                        this.unselectAllResult();
                    }

                    this.$el.find('.record-checkbox').each(function (i, elem) {
                        if (checked) {
                            this.checkRecord($(elem).data('id'), $(elem));
                        } else {
                            this.uncheckRecord($(elem).data('id'), $(elem));
                        }
                    }.bind(this));
                } else {
                    if (this.allResultIsChecked) {
                        this.unselectAllResult();
                    } else {
                        this.selectAllResult();
                    }
                }
                return;

                let checkbox = this.$el.find('.full-table').find('.select-all');
                let checkboxFixed = this.$el.find('.fixed-header-table').find('.select-all');

                if (!this.checkedAll) {
                    checkbox.prop('checked', true);
                    checkboxFixed.prop('checked', true);
                } else {
                    checkbox.prop('checked', false);
                    checkboxFixed.prop('checked', false);
                }

                this.selectAllHandler(e.currentTarget.checked);
                this.checkedAll = e.currentTarget.checked;
            },
            'click .action': function (e) {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] == 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            },
            'click tr': function (e) {
                if (e.target.tagName === 'TD' && !this.allResultIsChecked) {
                    const row = $(e.currentTarget);
                    const id = row.data('id');
                    const $target = row.find('.record-checkbox');

                    if ($target) {
                        if (!$target.prop('checked')) {
                            this.checkRecord(id);
                            this.checkIntervalRecords(e, $target);
                        } else {
                            this.uncheckRecord(id);
                        }
                    }
                }
            },
            'click .actions a.mass-action': function (e) {
                $el = $(e.currentTarget);
                var action = $el.data('action');

                var method = 'massAction' + Espo.Utils.upperCaseFirst(action);
                if (method in this) {
                    this[method]($el.data());
                } else {
                    this.massAction(action);
                }
            }
        },

        refreshLayout() {
            this.listLayout = null
            this._internalLayout = null
            this.notify('Loading...')
            this.getInternalLayout(() => {
                this.getSelectAttributeList(selectAttributeList => {
                    if (selectAttributeList) {
                        this.collection.data.select = selectAttributeList.join(',');
                    }
                    this.collection.fetch({ keepSelected: true })
                    this.collection.once('sync', () => {
                        this.notify(false);
                    })
                });
            })
        },

        checkIntervalRecords(e, $target) {
            const checked = this.$el.find('.record-checkbox:checked');
            const allCheckboxes = this.$el.find('.record-checkbox');
            if (e.shiftKey && checked.length > 1) {
                const elementIndexInArray = allCheckboxes.index($target);
                const firstCheckedElementIndexInArray = allCheckboxes.index(checked.eq(0));
                const lastCheckedElementIndexInArray = allCheckboxes.index(checked.eq(checked.length - 1));

                //set cycle indexes for checking interval records
                let startIndex = elementIndexInArray;
                let endIndex = lastCheckedElementIndexInArray;
                if (elementIndexInArray !== firstCheckedElementIndexInArray) {
                    startIndex = firstCheckedElementIndexInArray;
                    endIndex = elementIndexInArray;
                }

                for (let i = startIndex; i <= endIndex; i++) {
                    let checkbox = allCheckboxes.eq(i);
                    this.checkRecord(checkbox.data('id'), checkbox);
                }
            }
        },

        setupDraggableParams() {
            this.dragableListRows = this.dragableListRows || this.options.dragableListRows;
            this.listRowsOrderSaveUrl = this.listRowsOrderSaveUrl || this.options.listRowsOrderSaveUrl;

            const urlParts = (this.collection.url || '').split('/');
            const mainScope = urlParts[0];
            this.relationName = urlParts[2];
            if (mainScope && this.relationName) {
                const dragDropDefs = this.getMetadata().get(['clientDefs', mainScope, 'relationshipPanels', this.relationName, 'dragDrop']);
                if (dragDropDefs && (this.dragableListRows || typeof this.dragableListRows === 'undefined')) {
                    this.dragableListRows = dragDropDefs.isActive;
                    this.dragableSortField = dragDropDefs.sortField;
                    if (this.dragableSortField) {
                        this.collection.sortBy = this.dragableSortField;
                    }
                }
            }

            if (this.dragableListRows) {
                this.listenTo(this.collection, 'listSorted', () => this.collection.fetch());
            }
        },

        toggleSort: function (field) {
            var asc = true;
            if (field === this.collection.sortBy && this.collection.asc) {
                asc = false;
            }
            this.notify('Please wait...');
            this.collection.once('sync', function () {
                this.notify(false);
                this.trigger('sort', { sortBy: field, asc: asc });
            }, this);
            var maxSizeLimit = this.getConfig().get('recordListMaxSizeLimit') || 200;
            while (this.collection.length > maxSizeLimit) {
                this.collection.pop();
            }
            this.collection.sort(field, asc);
            this.deactivate();
        },

        selectAllHandler: function (isChecked) {
            this.checkedList = [];

            var $actionsButton = this.$el.find('.actions-button');

            if (isChecked) {
                this.$el.find('input.record-checkbox').prop('checked', true);
                $actionsButton.removeAttr('disabled');
                this.collection.models.forEach(function (model) {
                    this.checkedList.push(model.id);
                }, this);
                this.$el.find('.list > table tbody tr').addClass('active');
            } else {
                if (this.allResultIsChecked) {
                    this.unselectAllResult();
                }
                this.$el.find('input.record-checkbox').prop('checked', false);
                $actionsButton.attr('disabled', true);
                this.$el.find('.list > table tbody tr').removeClass('active');
            }

            this.trigger('check');
        }, /**
         * @param {string} or {bool} ['both', 'top', 'bottom', false, true] Where to display paginations.
         */
        pagination: false, /**
         * @param {bool} To dispaly table header with column names.
         */
        header: true,

        showMore: true,

        massActionList: ['remove', 'compare', 'merge', 'massUpdate', 'export'],

        checkAllResultMassActionList: ['remove', 'massUpdate', 'export'],

        quickDetailDisabled: false,

        quickEditDisabled: false, /**
         * @param {array} Columns layout. Will be convered in 'Bull' typed layout for a fields rendering.
         *
         */
        listLayout: null,

        _internalLayout: null,

        checkedList: null,

        buttonsDisabled: false,

        allResultIsChecked: false,

        hasLayoutEditor: false,

        data: function () {
            var paginationTop = this.pagination === 'both' || this.pagination === true || this.pagination === 'top';
            var paginationBottom = this.pagination === 'both' || this.pagination === true || this.pagination === 'bottom';

            const fixedHeaderRow = this.isFixedListHeaderRow();

            return {
                scope: this.scope,
                header: this.header,
                headerDefs: this._getHeaderDefs(),
                paginationEnabled: this.pagination,
                paginationTop: paginationTop,
                paginationBottom: paginationBottom,
                showMoreActive: this.collection.total > this.collection.length || this.collection.total == -1,
                showMoreEnabled: this.showMore,
                showCount: this.showCount && this.collection.total > 0,
                moreCount: this.collection.total - this.collection.length,
                checkboxes: this.checkboxes,
                allowSelectAllResult: this.isAllowedSelectAllResult(),
                massActionList: this.massActionList,
                rowList: this.rowList,
                topBar: paginationTop || this.checkboxes || this.showSearch || this.showFilter || (this.buttonList.length && !this.buttonsDisabled) || fixedHeaderRow,
                bottomBar: paginationBottom,
                buttonList: this.buttonList,
                displayTotalCount: this.displayTotalCount && (this.collection.total == null || this.collection.total >= 0),
                totalLoading: this.collection.total == null,
                countLabel: this.getShowMoreLabel(),
                showNoData: !this.collection.length && !fixedHeaderRow
            };
        },

        isAllowedSelectAllResult() {
            if (this.options.allowSelectAllResult === false) {
                return false;
            }

            if (this.getParentView() && this.getParentView().getParentView()) {
                let view = this.getParentView().getParentView();

                if (view.panelName && this.getMetadata().get(`clientDefs.${view.model.name}.relationshipPanels.${view.panelName}.disabledSelectAllResult`)) {
                    return false;
                }

                if (view.fieldType && view.fieldType === 'linkMultiple') {
                    if (!view.mode || view.mode !== 'search') {
                        return false;
                    }
                }
            }

            return true;
        },

        isFixedListHeaderRow() {
            let parent = this.getParentView();

            if (!parent) {
                return false;
            }

            // check for entity list view
            if (parent.$el.is('#main') && this.checkboxes) {
                return true;
            }

            // check for entity list modal view
            return parent.$el.is('.modal-container') || parent.$el.is('.modal-body');
        },

        getShowMoreLabel() {
            let label = this.getLanguage().translate('Show more');

            if (this.showCount && this.collection.total > 0) {
                let limit = this.collection.maxSize;
                let add = this.collection.total - this.collection.length;

                if (limit < add) {
                    add = limit;
                }

                label = this.getLanguage().translate('Show %s more').replace('%s', add);
            }

            return label;
        },

        init: function () {
            this.listLayout = this.options.listLayout || this.listLayout;
            this.layoutData = this.options.layoutData || this.layoutData
            this.type = this.options.type || this.type;

            this.layoutName = this.options.layoutName || this.layoutName || this.type;

            this.headerDisabled = this.options.headerDisabled || this.headerDisabled;
            if (!this.headerDisabled) {
                this.header = _.isUndefined(this.options.header) ? this.header : this.options.header;
            } else {
                this.header = false;
            }
            this.pagination = _.isUndefined(this.options.pagination) ? this.pagination : this.options.pagination;
            this.checkboxes = _.isUndefined(this.options.checkboxes) ? this.checkboxes : this.options.checkboxes;
            this.selectable = _.isUndefined(this.options.selectable) ? this.selectable : this.options.selectable;
            this.rowActionsView = _.isUndefined(this.options.rowActionsView) ? this.rowActionsView : this.options.rowActionsView;
            this.rowActionsColumnWidth = _.isUndefined(this.options.rowActionsColumnWidth) ? this.rowActionsColumnWidth : this.options.rowActionsColumnWidth;
            this.showMore = _.isUndefined(this.options.showMore) ? this.showMore : this.options.showMore;

            this.massActionsDisabled = this.options.massActionsDisabled || this.massActionsDisabled;

            if (this.massActionsDisabled && !this.selectable) {
                this.checkboxes = false;
            }

            this.rowActionsDisabled = this.options.rowActionsDisabled || this.rowActionsDisabled;

            if ('buttonsDisabled' in this.options) {
                this.buttonsDisabled = this.options.buttonsDisabled;
            }
        },

        getModelScope: function (id) {
            return this.scope;
        },

        selectAllResult: function () {
            this.allResultIsChecked = true;
            this.checkedList = [];

            this.$el.find('input.record-checkbox').prop('checked', true).attr('disabled', 'disabled');
            this.$el.find('input.select-all').prop('checked', true);

            this.massActionList.forEach(function (item) {
                if (!~this.checkAllResultMassActionList.indexOf(item)) {
                    this.$el.find('div.list-buttons-container .actions li a.mass-action[data-action="' + item + '"]').parent().addClass('hidden');
                }
            }, this);

            if (this.checkAllResultMassActionList.length) {
                this.$el.find('.actions-button').removeAttr('disabled');
            }

            this.$el.find('.list > table tbody tr').removeClass('active');

            this.$el.find('.selected-count').removeClass('hidden');
            this.$el.find('.selected-count > .selected-count-span').text(this.collection.total);

            this.trigger('select-all-results');
        },

        unselectAllResult: function () {
            this.allResultIsChecked = false;
            this.checkedList = [];

            this.$el.find('input.record-checkbox').prop('checked', false).removeAttr('disabled');
            this.$el.find('input.select-all').prop('checked', false);

            this.$el.find('.selected-count').addClass('hidden');
            this.$el.find('.selected-count > .selected-count-span').text(0);

            if (this.checkAllResultMassActionList.length) {
                this.$el.find('.actions-button').attr('disabled', true);
            }

            this.massActionList.forEach(function (item) {
                if (!~this.checkAllResultMassActionList.indexOf(item)) {
                    this.$el.find('div.list-buttons-container .actions li a.mass-action[data-action="' + item + '"]').parent().removeClass('hidden');
                }
            }, this);
        },

        deactivate: function () {
            if (this.$el) {
                this.$el.find(".pagination li").addClass('disabled');
                this.$el.find("a.sort").addClass('disabled');
            }
        },

        massActionExport: function () {
            let data = {};
            if (this.allResultIsChecked) {
                data.where = this.collection.getWhere();
                data.selectData = this.collection.data || {};
                data.byWhere = true;
            } else {
                data.ids = this.checkedList;
            }

            let o = {
                scope: this.entityType,
                entityFilterData: data
            };

            var layoutFieldList = [];
            (this.listLayout || []).forEach(function (item) {
                if (item.name) {
                    layoutFieldList.push(item.name);
                }
            }, this);
            o.fieldList = layoutFieldList;

            this.createView('dialogExport', 'views/export/modals/export', o, function (view) {
                view.render();
            }, this);
        },

        massAction: function (name) {
            var bypassConfirmation = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'bypassConfirmation']);
            var confirmationMsg = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'confirmationMessage']) || 'confirmation';

            var proceed = function () {
                var acl = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'acl']);
                var aclScope = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'aclScope']);

                if (acl || aclScope) {
                    if (!this.getAcl().check(aclScope || this.scope, acl)) {
                        this.notify('Access denied', 'error');
                        return;
                    }
                }

                var idList = [];
                var data = {};

                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhere();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.idList = idList;
                }

                for (var i in this.checkedList) {
                    idList.push(this.checkedList[i]);
                }

                data.entityType = this.entityType;

                var waitMessage = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'waitMessage']) || 'pleaseWait';
                Espo.Ui.notify(this.translate(waitMessage, 'messages', this.scope));

                var url = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'url']);

                this.ajaxPostRequest(url, data).then(function (result) {
                    var successMessage = result.successMessage || this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', name, 'successMessage']) || 'done';

                    this.collection.fetch().then(function () {
                        var message = this.translate(successMessage, 'messages', this.scope);
                        if (typeof result === 'object' && 'count' in result) {
                            message = message.replace('{count}', result.count);
                        }
                        Espo.Ui.success(message);
                    }.bind(this));
                }.bind(this));
            }

            if (!bypassConfirmation) {
                this.confirm(this.translate(confirmationMsg, 'messages', this.scope), proceed, this);
            } else {
                proceed.call(this);
            }
        },

        getActionDefs(id) {
            let defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).find(defs => defs.id === id)
            if (!defs) {
                defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicEntityActions']) || []).find(defs => defs.id === id)
            }
            return defs
        },

        massActionDynamicMassAction: function (data) {
            const defs = this.getActionDefs(data.id)

            if (defs && defs.type) {
                const method = 'massActionDynamicAction' + Espo.Utils.upperCaseFirst(defs.type);
                if (typeof this[method] == 'function') {
                    this[method].call(this, data);
                    return
                }
            }

            this.executeDynamicMassActionRequest(data)
        },

        executeDynamicMassActionRequest(data) {
            let requestData = {
                actionId: data.id
            };

            if (this.allResultIsChecked) {
                requestData.where = this.collection.getWhere();
                requestData.massAction = true;
            } else if (this.checkedList && this.checkedList.length > 0) {
                requestData.where = [{ type: "in", attribute: "id", value: this.checkedList }];
                requestData.massAction = true;
            }

            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow', requestData).success(response => {
                if (response.inBackground) {
                    this.notify(this.translate('jobAdded', 'messages'), 'success');
                    setTimeout(() => {
                        this.collection.fetch();
                    }, 3000);
                } else {
                    if (response.success) {
                        this.notify(response.message, 'success');
                    } else {
                        this.notify(response.message, 'error');
                    }
                    this.collection.fetch();
                }
            });
        },

        massActionRemove: function (data, permanently = false) {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            let action = () => {
                this.notify(this.translate('removing', 'labels', 'Global'));

                var ids = [];
                var data = { permanently: permanently };
                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhere();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.ids = ids;
                }

                for (var i in this.checkedList) {
                    ids.push(this.checkedList[i]);
                }

                $.ajax({
                    url: this.entityType + '/action/massDelete',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    this.notify(false)
                    this.processMassActionResult(result)
                    this.collection.fetch();
                }.bind(this));
            }
            if (!permanently && this.getMetadata().get(['scopes', this.scope, 'deleteWithoutConfirmation'])
                && ((!this.allResultIsChecked && this.checkedList.length === 1) || this.collection.length === 1)) {
                action();
                return;
            }

            this.confirm({
                message: this.prepareRemoveSelectedRecordsConfirmationMessage(permanently ? 'deletePermanentlyRecordsConfirmation' : 'removeSelectedRecordsConfirmation'),
                confirmText: this.translate('Remove')
            }, function () {

                action();

            }, this);
        },

        massActionDeletePermanently() {
            this.massActionRemove(null, true);
        },

        massActionRestore: function () {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            this.confirm({
                message: this.prepareRestoreSelectedRecordsConfirmationMessage(),
                confirmText: this.translate('Restore')
            }, function () {
                this.notify(this.translate('restoring', 'labels', 'Global'));

                var ids = [];
                var data = {};
                if (this.allResultIsChecked) {
                    data.where = this.collection.getWhere();
                    data.selectData = this.collection.data || {};
                    data.byWhere = true;
                } else {
                    data.ids = ids;
                }

                for (var i in this.checkedList) {
                    ids.push(this.checkedList[i]);
                }

                $.ajax({
                    url: this.entityType + '/action/massRestore',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    this.collection.fetch().then(() => this.notify(this.translate('Restored'), 'success'));
                }.bind(this));
            }, this);
        },

        prepareRemoveSelectedRecordsConfirmationMessage: function (key) {
            let scopeMessage = this.getMetadata()
                .get(`clientDefs.${this.scope}.${key}`)
                ?.split('.');
            let message = this.translate(key, 'messages');
            if (scopeMessage?.length > 0) {
                message = this.translate(scopeMessage.pop(), scopeMessage.pop(), scopeMessage.pop());
                var selectedIds = this.checkedList;
                var selectedNames = this.collection.models
                    .filter(function (model) {
                        return selectedIds.includes(model.id);
                    })
                    .map(function (model) {
                        return "'" + model.attributes['name'] + "'";
                    })
                    .join(", ");
                message = message.replace('{{selectedNames}}', selectedNames);
            }
            return message;
        },
        prepareRestoreSelectedRecordsConfirmationMessage: function () {
            let scopeMessage = this.getMetadata()
                .get(`clientDefs.${this.scope}.restoreSelectedRecordsConfirmation`)
                ?.split('.');
            let message = this.translate('restoreSelectedRecordsConfirmation', 'messages');
            if (scopeMessage?.length > 0) {
                message = this.translate(scopeMessage.pop(), scopeMessage.pop(), scopeMessage.pop());
                var selectedIds = this.checkedList;
                var selectedNames = this.collection.models
                    .filter(function (model) {
                        return selectedIds.includes(model.id);
                    })
                    .map(function (model) {
                        return "'" + model.attributes['name'] + "'";
                    })
                    .join(", ");
                message = message.replace('{{selectedNames}}', selectedNames);
            }
            return message;
        },

        massActionFollow: function () {
            var count = this.checkedList.length;

            var ids = [];
            var data = {};
            if (this.allResultIsChecked) {
                data.where = this.collection.getWhere();
                data.selectData = this.collection.data || {};
                data.byWhere = true;
            } else {
                data.ids = ids;
            }

            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }

            var confirmMsg = this.translate('confirmMassFollow', 'messages').replace('{count}', count.toString());
            this.confirm({
                message: confirmMsg,
                confirmText: this.translate('Follow')
            }, function () {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest(this.entityType + '/action/massFollow', data).then(function (result) {
                    var resultCount = result.count || 0;
                    var msg = 'massFollowResult';
                    if (resultCount) {
                        if (resultCount === 1) {
                            msg += 'Single';
                        }
                        Espo.Ui.success(this.translate(msg, 'messages').replace('{count}', resultCount));
                    } else {
                        Espo.Ui.warning(this.translate('massFollowZeroResult', 'messages'));
                    }
                }.bind(this));
            }, this);
        },

        massActionUnfollow: function () {
            var count = this.checkedList.length;

            var ids = [];
            var data = {};
            if (this.allResultIsChecked) {
                data.where = this.collection.getWhere();
                data.selectData = this.collection.data || {};
                data.byWhere = true;
            } else {
                data.ids = ids;
            }

            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }

            var confirmMsg = this.translate('confirmMassUnfollow', 'messages').replace('{count}', count.toString());
            this.confirm({
                message: confirmMsg,
                confirmText: this.translate('Unfollow')
            }, function () {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest(this.entityType + '/action/massUnfollow', data).then(function (result) {
                    var resultCount = result.count || 0;
                    var msg = 'massUnfollowResult';
                    if (resultCount) {
                        if (resultCount === 1) {
                            msg += 'Single';
                        }
                        Espo.Ui.success(this.translate(msg, 'messages').replace('{count}', resultCount));
                    } else {
                        Espo.Ui.warning(this.translate('massUnfollowZeroResult', 'messages'));
                    }
                }.bind(this));
            }, this);
        },

        massActionMerge: function (data, e) {
            return this.massActionCompare(data, e, true);
        },

        processMassActionResult(result) {
            if (result.sync) {
                if (result.errors && result.errors.length > 0) {
                    let error = result.errors.slice(0, 19).join('<br>');
                    if (result.errors.length > 20) {
                        error += '<br> ' + (result.errors.length - 20) + ' more errors'
                    }
                    Espo.ui.error(error);
                } else {
                    Espo.Ui.success(this.translate('Done'));
                }
            } else {
                Espo.Ui.success(this.translate('massActionDelegatedToJm'));
            }
        },

        massActionMassUpdate: function () {
            if (!this.getAcl().check(this.entityType, 'edit')) {
                this.notify('Access denied', 'error');
                return false;
            }

            Espo.Ui.notify(this.translate('loading', 'messages'));
            var ids = false;
            var allResultIsChecked = this.allResultIsChecked;
            if (!allResultIsChecked) {
                ids = this.checkedList;
            }

            let massUpdateView = this.getMetadata().get(['clientDefs', this.scope, 'massUpdateView']) || 'views/modals/mass-update';

            this.createView('massUpdate', massUpdateView, {
                scope: this.entityType,
                ids: ids,
                where: this.collection.getWhere(),
                selectData: this.collection.data,
                byWhere: this.allResultIsChecked
            }, function (view) {
                view.render();
                view.notify(false);
                view.once('after:update', function (result) {
                    view.close();
                    this.listenToOnce(this.collection, 'sync', function () {
                        this.processMassActionResult(result)
                        if (allResultIsChecked) {
                            this.selectAllResult();
                        } else {
                            ids.forEach(function (id) {
                                this.checkRecord(id);
                            }, this);
                        }
                    }.bind(this));
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        massActionCompare: function (data, e, merging = false) {
            if (!this.getAcl().check(this.entityType, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }

            if (this.checkedList.length < 2) {
                this.notify('Select 2 or more records', 'error');
                return;
            }
            if (this.checkedList.length > 10) {
                this.notify(this.translate('selectNoMoreThan', 'messages').replace('{count}', 10), 'error');
                return;
            }

            let collection = this.collection.clone();
            collection.url = this.entityType;
            collection.where = [
                {
                    attribute: 'id',
                    type: 'in',
                    value: this.checkedList
                }
            ];

            this.notify(this.translate('Loading'))
            collection.fetch().success(() => {
                let view = this.getMetadata().get(['clientDefs', this.entityType, 'modalViews', 'compare']) || 'views/modals/compare'
                this.createView('dialog', view, {
                    collection: collection,
                    scope: this.entityType,
                    merging: merging
                }, function (dialog) {
                    dialog.render();
                    this.notify(false);
                    this.listenTo(dialog, 'merge-success', () => this.collection.fetch());
                })
            });
        },

        removeMassAction: function (item) {
            var index = this.massActionList.indexOf(item);
            if (~index) {
                this.massActionList.splice(index, 1);
            }
        },

        addMassAction: function (item, allResult) {
            this.massActionList.push(item);
            if (allResult) {
                this.checkAllResultMassActionList.push(item);
            }
        },

        setup: function () {
            this.setupDraggableParams();
            if (typeof this.collection === 'undefined') {
                throw new Error('Collection has not been injected into Record.List view.');
            }

            this.layoutLoadCallbackList = [];

            this.entityType = this.collection.name || null;
            this.scope = this.options.scope || this.entityType;
            this.events = Espo.Utils.clone(this.events);
            this.massActionList = Espo.Utils.clone(this.massActionList);
            this.buttonList = Espo.Utils.clone(this.buttonList);
            this.relationScope = this.getRelationScope()

            if (this.options.searchManager) {
                this.searchManager = this.options.searchManager;
            }

            if (typeof this.options.showFilter === 'boolean') {
                this.showFilter = this.options.showFilter;
            }

            if (typeof this.options.showSearch === 'boolean') {
                this.showSearch = this.options.showSearch;
            }

            if (typeof this.options.searchUniqueKey === 'string') {
                this.uniqueKey = this.options.searchUniqueKey;
            }

            if (this.getMetadata().get(['scopes', this.getModelScope(), 'disabled'])) {
                this.checkboxes = false;
            }

            if (!this.getAcl().checkScope(this.entityType, 'delete')) {
                this.removeMassAction('remove');
                this.removeMassAction('merge');
            }

            if (!this.getAcl().checkScope(this.entityType, 'edit')) {
                this.removeMassAction('massUpdate');
                this.removeMassAction('merge');
            }

            (this.getMetadata().get(['clientDefs', this.scope, 'massActionList']) || []).forEach(function (item) {
                var defs = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', item]) || {};
                var acl = defs.acl;
                var aclScope = defs.aclScope;
                if (acl || aclScope) {
                    if (!this.getAcl().check(aclScope || this.scope, acl)) {
                        return;
                    }
                }
                var configCheck = defs.configCheck;
                if (configCheck) {
                    var arr = configCheck.split('.');
                    if (!this.getConfig().getByPath(arr)) {
                        return;
                    }
                }
                this.massActionList.push(item);
            }, this);

            var checkAllResultMassActionList = [];
            this.checkAllResultMassActionList.forEach(function (item) {
                if (~this.massActionList.indexOf(item)) {
                    checkAllResultMassActionList.push(item);
                }
            }, this);
            this.checkAllResultMassActionList = checkAllResultMassActionList;

            (this.getMetadata().get(['clientDefs', this.scope, 'checkAllResultMassActionList']) || []).forEach(function (item) {
                if (~this.massActionList.indexOf(item)) {
                    var defs = this.getMetadata().get(['clientDefs', this.scope, 'massActionDefs', item]) || {};
                    var acl = defs.acl;
                    var aclScope = defs.aclScope;
                    if (acl || aclScope) {
                        if (!this.getAcl().check(aclScope || this.scope, acl)) {
                            return;
                        }
                    }
                    var configCheck = defs.configCheck;
                    if (configCheck) {
                        var arr = configCheck.split('.');
                        if (!this.getConfig().getByPath(arr)) {
                            return;
                        }
                    }
                    this.checkAllResultMassActionList.push(item);
                }
            }, this);

            if (
                !this.massFollowDisabled &&
                this.getMetadata().get(['scopes', this.entityType, 'stream']) &&
                this.getAcl().check(this.entityType, 'stream')
            ) {
                this.addMassAction('follow', true);
                this.addMassAction('unfollow', true);
            }

            this.setupMassActionItems();

            let dynamicActions = [];
            (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).forEach(dynamicAction => {
                if (this.getAcl().check(dynamicAction.acl.scope, dynamicAction.acl.action) && dynamicAction.massAction) {
                    let obj = {
                        action: "dynamicMassAction",
                        label: dynamicAction.name,
                        id: dynamicAction.id
                    };
                    dynamicActions.push(obj)
                }
            });
            if (dynamicActions.length > 0) {
                dynamicActions = dynamicActions.sort((v1, v2) => {
                    return v1.label.localeCompare(v2.label);
                })
                dynamicActions.unshift({ divider: true })
                this.massActionList.push(...dynamicActions);
                this.checkAllResultMassActionList.push(...dynamicActions);
            }


            if (this.selectable) {
                this.events['click .list a.link'] = function (e) {
                    e.preventDefault();
                    var id = $(e.target).data('id');
                    if (id) {
                        var model = this.collection.get(id);
                        if (this.checkboxes) {
                            var list = [];
                            list.push(model);
                            this.trigger('select', list);
                        } else {
                            this.trigger('select', model);
                        }
                    }
                    e.stopPropagation();
                };
            }

            if ('showCount' in this.options) {
                this.showCount = this.options.showCount;
            }
            this.displayTotalCount = this.showCount && this.getConfig().get('displayListViewRecordCount');

            if ('displayTotalCount' in this.options) {
                this.displayTotalCount = this.options.displayTotalCount;
            }

            const scopeType = this.getMetadata().get(['scopes', this.scope, 'type']);
            if (this.options.massActionsDisabled || scopeType === 'ReferenceData') {
                this.massActionList = [];
            }

            if (!this.massActionList.length && !this.selectable) {
                this.checkboxes = false;
            }

            //After investigation,  sometimes listenTo doesn't work so listening directly works, I can't explain why
            this.collection.on('sync', (c, r, options) => {
                if (this.hasView('modal') && this.getView('modal').isRendered()) return;
                if (this.noRebuild) {
                    this.noRebuild = null;
                    return;
                }

                if (options && typeof options.noRebuild == 'function') {
                    if (options.noRebuild()) {
                        return;
                    }
                }

                if (!options || !options.keepSelected) {
                    this.checkedList = [];
                    this.allResultIsChecked = false;
                }
                this.buildRows(function () {
                    this.render();
                }.bind(this));
            });

            this.checkedList = [];
            if (!this.options.skipBuildRows) {
                this.buildRows();
            }

            this.enabledFixedHeader = this.options.enabledFixedHeader || this.enabledFixedHeader;
            this.baseWidth = [];


            this.hasLayoutEditor = !!this.getMetadata().get(['scopes', this.scope, 'layouts']) && 'list' === this.layoutName &&
                this.getAcl().check('LayoutProfile', 'read');

            this.listenTo(this, 'after:save', () => {
                this.afterSave();
            });

            this.listenTo(this.collection, 'sync', () => {
                let $shown = this.$el.find('.shown-count-span');
                if ($shown.length > 0) {
                    $shown.html(this.collection.length);
                }
            });
            this.listenTo(this.collection, 'update-total', () => {
                if (this.collection.total > this.collection.length || this.collection.total === -1) {
                    this.$el.find('.show-more').removeClass('hidden')
                    this.$el.find('.show-more .more-label').text(this.getShowMoreLabel())
                } else {
                    this.$el.find('.show-more').addClass('hidden')
                }

                if (this.collection.total != null) {
                    this.$el.find('.list-buttons-container .preloader').addClass('hidden')
                    this.$el.find('.list-buttons-container .total-count').removeClass('hidden')
                    if (this.collection.total >= 0) {
                        this.$el.find('.total-count-span').html(this.collection.total)
                        this.$el.find('.shown-count-span').html(this.collection.length)
                    }
                } else {
                    this.$el.find('.list-buttons-container .preloader').removeClass('hidden')
                    this.$el.find('.list-buttons-container .text-count').addClass('hidden')
                }

            });

            $(window).on(`keydown.${this.cid} keyup.${this.cid}`, e => {
                document.onselectstart = function () {
                    return !e.shiftKey;
                }
            });

            this.dragndropEventName = `resize.drag-n-drop-table-${this.cid}`;
            this.listenToOnce(this, 'remove', () => {
                $(window).off(this.dragndropEventName);
                $(window).off(`keydown.${this.cid} keyup.${this.cid}`);
            });


            if (!this.options.disableRefreshOnLanguageChange) {
                this.addToLanguageObservables();
                this.listenTo(this, 'change:disabled-languages', () => {
                    this.refreshLayout()
                })
            }
        },

        afterSave: function () {
            this.collection.fetch();
        },

        canRenderSearch: function () {
            return this.searchManager && (this.showSearch || this.showFilter)
        },

        afterRender: function () {
            this.createLayoutConfigurator();

            try {
                this.svelteFilter?.$destroy();
            } catch (e) {}

            const target = document.querySelector(this.options.el + ' .list-buttons-container .filter-container');
            if (target && this.canRenderSearch()) {
                const props = {
                    searchManager: this.searchManager,
                    showSearchPanel: this.showSearch,
                    showFilter: this.showFilter,
                    scope: this.scope,
                };

                if (this.uniqueKey) {
                    props.uniqueKey = this.uniqueKey;
                }

                this.svelteFilter = new Svelte.FilterSearchBar({
                    target: target,
                    props: props
                })
            }

            if (this.allResultIsChecked) {
                this.selectAllResult();
            } else {
                if (this.checkedList.length) {
                    this.checkedList.forEach(function (id) {
                        this.checkRecord(id);
                    }, this);
                }
            }

            let list = $('#main .list-container > .list');
            if (!list) {
                return;
            }

            this.fullTableScroll();

            if (this.enabledFixedHeader) {
                this.fixedTableHead()
            }

            if (this.hasHorizontalScroll()) {
                var scrollTimeout = null;

                $(window).on("scroll.fixed-scrollbar", function () {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(function () {
                        list.each(function () {
                            var $container = $(this);
                            var $bar = $container.children('.fixed-scrollbar');

                            if ($bar.length) {
                                var containerOffset = {
                                    top: $container.offset().top,
                                    bottom: $container.offset().top + $container.height()
                                };
                                var windowOffset = {
                                    top: $(window).scrollTop(),
                                    bottom: $(window).scrollTop() + $(window).height()
                                };

                                if ((containerOffset.top > windowOffset.bottom) || (windowOffset.bottom > containerOffset.bottom)) {
                                    if ($bar.data("status") === "on") {
                                        $bar.hide().data("status", "off");
                                    }
                                } else {
                                    if ($bar.data("status") === "off") {
                                        $bar.show().data("status", "on");
                                        $bar.scrollLeft($container.scrollLeft());
                                    }
                                }
                            }
                        });
                    }, 50);
                });

                $(window).trigger("scroll.fixed-scrollbar");
            }

            this.changeDropDownPosition();

            if (this.dragableListRows && !((this.getParentView() || {}).defs || {}).readOnly) {
                let allowed = true;
                (this.collection.models || []).forEach(model => {
                    if (this.getAcl().checkModel(model, 'edit') === false) {
                        allowed = false;
                    }
                });

                if (!allowed) {
                    $("td[data-name='draggableIcon'] span").remove();
                }

                if (allowed) {
                    this.initDraggableList();
                    $(window).off(this.dragndropEventName).on(this.dragndropEventName, () => {
                        this.initDraggableList();
                    });
                }
            }

            if (this.showMore) {
                setTimeout(function () {
                    if (this.$el.parent().hasClass('modal-body')) {
                        let parent = this.$el.parent();

                        parent.off('scroll');
                        parent.on('scroll', parent, function () {
                            if (this.collection.total > this.collection.length + this.collection.lengthCorrection && parent.scrollTop() + parent.outerHeight() >= parent.get(0).scrollHeight - 50) {
                                let type = 'list';
                                if (this.isHierarchical()) {
                                    type = this.getStorage().get('list-small-view-type', this.scope) || 'tree'
                                }

                                let btn = parent.find('a[data-action="showMore"]');
                                if (type === 'tree') {
                                    btn = parent.find('li.show-more > div.btn');
                                }

                                this.loadMore(btn);
                            }
                        }.bind(this));
                    } else if (this.$el.parent().prop('id') === 'main' || (this.$el.parent().prop("tagName") || '').toLowerCase() === 'main') {
                        const content = this.$el.parent();

                        content.off('scroll', this.$el);
                        content.on('scroll', this.$el, function () {
                            if (this.collection.total > this.collection.length + this.collection.lengthCorrection && content.scrollTop() + content.height() >= content.get(0).scrollHeight - 50) {
                                this.loadMore(this.$el.find('a[data-action="showMore"]'));
                            }
                        }.bind(this));
                    }
                }.bind(this), 50)
            }
            const filters = this.getStorage().get('listQueryBuilder', this.scope) || {};
            if (filters.bool && filters.bool['onlyDeleted'] === true && !this.massActionList.includes('restore')) {
                this.massActionListBackup = this.massActionList;
                this.checkAllResultMassActionListBackup = this.checkAllResultMassActionList;
                this.massActionList = ['restore', 'deletePermanently'];
                this.checkAllResultMassActionList = ['restore', 'deletePermanently'];
                this.reRender();
            }

            if (filters.bool && filters.bool['onlyDeleted'] !== true && this.massActionList.includes('restore')) {
                this.massActionList = this.massActionListBackup;
                this.checkAllResultMassActionList = this.checkAllResultMassActionListBackup
                this.reRender()
            }

            if (!this.hasSetupTourButton) {
                this.setupTourButton();
                this.hasSetupTourButton = true
            }
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy'
                && this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`) !== true;
        },
        loadMore(btn) {
            if (btn.length && !btn.hasClass('disabled')) {
                btn.click();
            }
        },

        hasHorizontalScroll() {
            let list = this.$el.find('.list').get(0);
            let table = this.$el.find('.full-table').get(0);

            if (list && table) {
                if (list.clientWidth < table.clientWidth) {
                    return true;
                }
            }

            return false;
        },

        getHeightParentPosition() {
            let position;
            let parent = this.getParentView();

            if (parent && parent.viewMode === 'list' && this.$el.find('.list')) {
                let list = this.$el.find('.list');
                position = list.offset().top + list.get(0).clientHeight;
            } else {
                position = $(document).height();
            }

            return position;
        },

        getPositionFromBottom(element) {
            var rect = element.getBoundingClientRect();
            var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            var elementBottom = rect.bottom + scrollTop;
            var parentBottom = document.documentElement.scrollHeight;

            return parentBottom - elementBottom;
        },

        changeDropDownPosition() {
            let el = this.$el;
            el.on('show.bs.dropdown', function (e) {
                let target = e.relatedTarget;
                if ($(target).hasClass('actions-button')) {
                    return;
                }
                let menu = $(target).siblings('.dropdown-menu');
                if (target && menu) {
                    let menuHeight = menu.height();
                    let positionTop = $(target).offset().top + $(target).outerHeight(true);
                    let list = this.$el.find('.list');
                    let listPositionTop = list.offset().top;
                    const parentPosition = this.getHeightParentPosition()
                    if ((positionTop + menuHeight) > parentPosition) {
                        if (menuHeight <= (positionTop - listPositionTop)) {
                            menu.css({
                                "top": `-${menuHeight}px`
                            })
                        } else {
                            let rightOffset = $(document).width() - $(target).offset().left - $(target).outerHeight(true),
                                topOffset = window.innerHeight < (positionTop + menuHeight) ? window.innerHeight - (parentPosition - $(target).offset().top) - menuHeight : positionTop;
                            menu.css({
                                'position': 'fixed',
                                'top': `${topOffset}px`,
                                'right': `${rightOffset}px`
                            });
                        }
                    }

                    const cellButtons = $(target).closest('.cell[data-name=buttons]');
                    if (cellButtons.length) {
                        cellButtons.css('z-index', 1);
                    }
                }
            }.bind(this));

            el.on('hide.bs.dropdown', function (e) {
                $(e.relatedTarget).next('.dropdown-menu').removeAttr('style');

                const cellButtons = $(e.relatedTarget).closest('.cell[data-name=buttons]');
                if (cellButtons.length) {
                    cellButtons.css('z-index', '');
                }
            });
        },

        initDraggableList() {
            if (this.getAcl().check(this.scope, 'edit')) {
                this.$el.find(this.listContainerEl).sortable({
                    handle: window.innerWidth < 768 ? '.cell[data-name="draggableIcon"]' : false,
                    delay: 150,
                    update: function (e, ui) {
                        this.saveListItemOrder(e, ui);
                    }.bind(this),
                    helper: "clone",
                    start: (e, ui) => {
                        const widthData = {};

                        ui.placeholder.children().each(function (i, cell) {
                            widthData[i] = $(this).outerWidth();
                        });
                        ui.helper.children().each(function (i, cell) {
                            let width = widthData[i] ?? $(this).outerWidth();
                            $(this).css('width', width);
                        });
                    },
                    stop: (e, ui) => {
                        ui.item.children().each(function (i, cell) {
                            $(this).css('width', '');
                        });
                    }
                });
            }
        },

        saveListItemOrder(e, ui) {
            let url;
            let data;
            let parentView = this.getParentView();
            let link = null;
            let parentModel = null;
            if (parentView) {
                if (parentView.link) {
                    link = parentView.link;
                }
                if (parentView.model) {
                    parentModel = parentView.model;
                }
            }

            if (this.dragableSortField) {
                const itemId = this.getItemId(ui);
                if (itemId && parentModel) {
                    const sortFieldValue = this.getSortFieldValue(itemId);
                    url = `${this.scope}/${itemId}`;

                    let ids = this.getIdsFromDom();

                    let previousItemId = null;
                    let tmp = null;
                    ids.forEach(id => {
                        if (id === itemId) {
                            previousItemId = tmp;
                        }
                        tmp = id;
                    });

                    data = {
                        _scope: parentModel.urlRoot,
                        _id: parentModel.id,
                        _link: link,
                        _sortedIds: ids,
                        _itemId: itemId,
                        _previousItemId: previousItemId,
                        [this.dragableSortField]: sortFieldValue
                    };
                }
            } else if (this.listRowsOrderSaveUrl) {
                url = this.listRowsOrderSaveUrl;
                data = {
                    ids: this.getIdsFromDom()
                };
            }
            if (url) {
                this.ajaxPutRequest(url, data)
                    .then(response => {
                        let statusMsg = 'Error occurred';
                        let type = 'error';
                        if (response) {
                            statusMsg = 'Saved';
                            type = 'success';
                        }
                        this.notify(statusMsg, type, 3000);
                        if (parentModel) {
                            parentModel.trigger('after:dragDrop', link);
                        }
                    }, error => {
                        this.collection.fetch();
                    })
                    .always(() => this.collection.trigger('listSorted'));
            } else {
                this.collection.trigger('listSorted', this.getIdsFromDom());
            }
        },

        getItemId(ui) {
            let id;
            if (ui && ui.item) {
                id = ui.item.data('id');
            }
            return id;
        },

        getSortFieldValue(id) {
            let value;
            const ids = this.getIdsFromDom();
            const currIndex = ids.indexOf(id);
            if (currIndex > 0) {
                value = this.collection.get(ids[currIndex - 1]).get(this.dragableSortField) + 10;
            } else {
                value = this.collection.get(ids[currIndex + 1]).get(this.dragableSortField) - 10;
            }
            return value;
        },

        getIdsFromDom() {
            return $.map(this.$el.find(`${this.listContainerEl} tr`), function (item) {
                return $(item).data('id');
            });
        },

        fixedTableHead() {
            let $window = $(window),
                content = $('#main main'),
                list = $('#main main .list'),
                fixedTable = this.$el.find('.fixed-header-table'),
                fullTable = this.$el.find('.full-table'),
                navBarRight = $('.navbar-right'),
                fixedScroll = this.$el.find('.fixed-scrollbar'),
                posLeftTable = 0,
                navBarHeight = 0,

                setPosition = () => {
                    posLeftTable = fullTable.offset().left;
                    navBarHeight = navBarRight.outerHeight();

                    fixedTable.css({
                        'position': 'fixed',
                        'left': posLeftTable,
                        'top': navBarHeight - 1,
                        'right': 0,
                        'z-index': 2
                    });
                },
                setWidth = () => {
                    let widthTable = fullTable.outerWidth();

                    fixedTable.css('width', widthTable);

                    fullTable.find('thead').find('th').each(function (i, elem) {
                        let width = $(this).outerWidth();

                        if (!width) {
                            $(this).attr('width', 100);
                        }

                        fixedTable.find('th').eq(i).css('width', width);
                    });

                    fixedScroll.width(list.width()).children('div').height(1).width(list[0]?.scrollWidth);
                },
                toggleClass = () => {
                    let showPosition = fullTable.offset().top;

                    if ($window.scrollTop() > showPosition && $window.width() >= 768) {
                        fixedTable.removeClass('hidden');
                    } else {
                        fixedTable.addClass('hidden');
                    }
                };

            if (fullTable.length) {
                setPosition();
                setWidth();
                toggleClass();

                content.on('scroll', () => {
                    setPosition();
                    setWidth();
                    toggleClass();
                });
                $window.on('resize', function () {
                    this.fullTableScroll();
                    setPosition();
                    setWidth();
                }.bind(this));

                let observer = new ResizeObserver(() => {
                    this.fullTableScroll();

                    if (list) {
                        if (!this.hasHorizontalScroll() || $(window).width() < 768) {
                            this.$el.find('.fixed-scrollbar').css('display', 'none');
                        } else {
                            this.$el.find('.fixed-scrollbar').css('display', 'block');
                        }
                    }

                    setPosition();
                    setWidth();
                });
                observer.observe(content.get(0));

                this.listenToOnce(this, 'remove', () => {
                    observer.disconnect();
                });
            }
        },

        fullTableScroll() {

            let list = this.$el.find('.list');
            if (list.length) {
                let fixedTableHeader = list.find('.fixed-header-table');
                let fullTable = list.find('.full-table');
                let scroll = this.$el.find('.list > .panel-scroll');

                if (fullTable.length) {
                    if (scroll.length) {
                        scroll.scrollLeft(0);
                        scroll.addClass('hidden');
                    }

                    let $bar = this.$el.find('.fixed-scrollbar');
                    if ($bar.length === 0) {
                        $bar = $('<div class="fixed-scrollbar" style="display: none"><div></div></div>').appendTo(list).css({
                            width: list.outerWidth()
                        });

                        $bar.scroll(function () {
                            list.scrollLeft($bar.scrollLeft());
                        });
                    }

                    $bar.data("status", "off");
                    if (this.hasHorizontalScroll() && $(window).width() >= 768) {
                        $bar.css('display', 'block');
                    }

                    fullTable.find('thead').find('th').each(function (i, elem) {
                        let width = elem.width;

                        if (width) {
                            if (i in this.baseWidth) {
                                width = this.baseWidth[i];
                            }

                            if (typeof width === 'string' && width.match(/[0-9]*(%)/gm)) {
                                this.baseWidth[i] = width;
                                width = list.outerWidth() * parseInt(width) / 100;

                                if (width < 100) {
                                    width = 100;
                                }
                            }

                            elem.width = width;
                        }
                    }.bind(this));

                    fixedTableHeader.addClass('table-scrolled');
                    fullTable.addClass('table-scrolled');

                    let prevScrollLeft = 0;

                    list.off('scroll');
                    list.on('scroll', () => {
                        if (prevScrollLeft !== list.scrollLeft()) {
                            let fixedTableHeaderBasePosition = list.offset().left + 1 || 0;
                            fixedTableHeader.css('left', fixedTableHeaderBasePosition - list.scrollLeft());
                        }
                        prevScrollLeft = list.scrollLeft();
                    });
                }
            }
        },

        setupMassActionItems() {
            if (this.isAddRemoveRelationEnabled()) {
                let foreignEntities = this.getForeignEntities();
                if (foreignEntities.length) {
                    this.massActionList = Espo.Utils.clone(this.massActionList);
                    this.checkAllResultMassActionList = Espo.Utils.clone(this.checkAllResultMassActionList);
                    this.massActionList.push('addRelation');
                    this.massActionList.push('removeRelation');
                    this.checkAllResultMassActionList.push('addRelation');
                    this.checkAllResultMassActionList.push('removeRelation');
                }
            }

            if (this.getMetadata().get(['scopes', this.scope, 'hasAttribute']) && !this.getMetadata().get(['scopes', this.scope, 'disableAttributeLinking'])) {
                this.massActionList.push('removeAttribute');
                this.checkAllResultMassActionList.push('removeAttribute');
            }
        },

        isAddRemoveRelationEnabled() {
            let result = false;

            if (this.getMetadata().get(['scopes', this.scope, 'addRelationEnabled']) !== false) {
                let scope = this.getMetadata().get(['scopes', this.scope]);

                if (scope && scope.type && scope.type !== 'Relation' && scope.entity === true && scope.customizable !== false) {
                    result = true;
                }
            }

            return result;
        },

        getForeignEntities() {
            let foreignEntities = [];
            if (this.scope && this.getAcl().check(this.scope, 'edit')) {
                let links = this.getMetadata().get(['entityDefs', this.scope, 'links']) || {};
                let linkList = Object.keys(links).sort(function (v1, v2) {
                    return v1.localeCompare(v2);
                }.bind(this));

                linkList.forEach(link => {
                    let defs = links[link];

                    if (defs.foreign && defs.entity && this.getAcl().check(defs.entity, 'edit') && !defs.readOnly) {
                        let foreignType = this.getMetadata().get(['entityDefs', defs.entity, 'links', defs.foreign, 'type']);
                        if (this.checkRelationshipType(defs.type, foreignType)
                            && (this.getMetadata().get(['scopes', defs.entity, 'entity']) || defs.addRelationCustomDefs)
                            && !this.getMetadata().get(['scopes', defs.entity, 'disableMassRelation'])
                            && !defs.disableMassRelation) {
                            foreignEntities.push({
                                link: link,
                                entity: defs.entity,
                                addRelationCustomDefs: defs.addRelationCustomDefs
                            });
                        }
                    }
                });
            }
            return foreignEntities;
        },

        checkRelationshipType: function (type, foreignType) {
            if (type === 'hasMany') {
                if (foreignType === 'hasMany') {
                    return 'manyToMany';
                } else if (foreignType === 'belongsTo') {
                    return 'oneToMany';
                }
            }
        },

        massActionRemoveAttribute() {
            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'Attribute',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                allowSelectAllResult: true,
                boolFilterData: {
                    onlyForEntity: this.scope
                },
                boolFilterList: ['onlyForEntity'],
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', models => {
                    this.notify('Loading...');
                    let attributes = {
                        'ids': null,
                        'where': null
                    }

                    if (Array.isArray(models)) {
                        attributes.ids = models.map(m => m.id)
                    } else if (models.massRelate) {
                        attributes.where = models.where
                    }

                    let ids = null;
                    let where = null;
                    if (!this.allResultIsChecked) {
                        ids = this.checkedList;
                    } else {
                        where = this.collection.getWhere()
                    }

                    $.ajax({
                        url: this.scope + '/action/massRemoveAttribute',
                        type: 'POST',
                        data: JSON.stringify({
                            attributes,
                            ids: ids,
                            where: where,
                            byWhere: this.allResultIsChecked
                        }),
                        success: (result) => {
                            this.notify('A job is created to remove attributes', 'success');
                            this.checkMassActionJob(result.jobId)
                        },
                        error: () => {
                            this.notify('Error occurred', 'error');
                        }
                    });
                });
            });
        },

        checkMassActionJob(jobId) {
            let iteration = 1
            const handler = () => {
                $.ajax({
                    url: 'Job/action/massActionStatus?id=' + jobId,
                    type: 'GET',
                    success: (result) => {
                        if (result.done) {
                            if (result.errors) {
                                this.notify(result.message + '\n' + result.errors, 'error', 6000)
                            } else {
                                this.notify(result.message, 'success')
                            }

                            return
                        }

                        if (iteration < 3) {
                            iteration++
                            setTimeout(handler, 4000)
                        }
                    }
                });
            }

            setTimeout(handler, 3000)
        },

        massActionUpdateRelation(type) {
            let foreignEntities = this.getForeignEntities();
            if (!foreignEntities.length) {
                return;
            }

            this.notify('Loading...');
            this.getModelFactory().create(null, model => {
                model.set({
                    mainEntity: this.scope,
                    selectedLink: foreignEntities[0].link,
                    foreignEntities: foreignEntities
                });

                let view = this.getMetadata().get(['clientDefs', this.scope, 'massRelationView']) || this.massRelationView;
                this.createView('dialog', view, {
                    model: model,
                    mainCollection: this.collection,
                    multiple: true,
                    createButton: false,
                    scope: (foreignEntities[0].addRelationCustomDefs || {}).entity || foreignEntities[0].entity,
                    type: type,
                    checkedList: this.checkedList,
                    where: this.collection.getWhere(),
                    allResultIsChecked: this.allResultIsChecked
                }, view => {
                    view.render(() => {
                        this.notify(false);
                    });
                });
            });
        },

        massActionAddRelation() {
            this.massActionUpdateRelation('addRelation');
        },

        massActionRemoveRelation() {
            this.massActionUpdateRelation('removeRelation');
        },

        getParentModel() {
            let parentView = this.getParentView();
            return parentView?.options?.model
        },

        filterListLayout: function (listLayout) {
            if (this.dragableListRows && !((this.getParentView() || {}).defs || {}).readOnly && listLayout
                && Array.isArray(listLayout) && !listLayout.find(item => item.name === 'draggableIcon')) {
                listLayout = Espo.Utils.cloneDeep(listLayout);
                listLayout.unshift({
                    widthPx: '40',
                    align: 'center',
                    notSortable: true,
                    customLabel: '',
                    name: 'draggableIcon',
                    view: 'views/fields/draggable-list-icon'
                });
            }

            let parentModel = this.getParentModel();

            let entityType = null;
            let entityId = null;

            if (parentModel) {
                entityType = parentModel.urlRoot;
                entityId = parentModel.get('id');
            }

            listLayout = Espo.Utils.cloneDeep(listLayout);

            // remove relation virtual fields
            if (entityType) {
                let toRemove = [];
                listLayout.forEach((item, k) => {
                    let parts = item.name.split('__');
                    if (parts.length === 2) {
                        toRemove.push({ number: k, relEntity: parts[0] });
                    }
                });

                let relEntity = null;
                if (entityType) {
                    let relationName = this.getMetadata().get(['entityDefs', entityType, 'links', this.relationName, 'relationName']);
                    if (relationName) {
                        relEntity = relationName.charAt(0).toUpperCase() + relationName.slice(1);
                    }
                }

                toRemove.forEach(item => {
                    if (!relEntity || item.relEntity !== relEntity) {
                        listLayout.splice(item.number, 1);
                    }
                });
            }

            let filteredListLayout = [];

            listLayout.forEach(item => {
                let skip = this.getMetadata().get(`entityDefs.${this.entityType}.fields.${item.name}.listLayoutSkip`);

                if (skip) {
                    return;
                }

                if (entityType && this.getMetadata().get(`entityDefs.${this.entityType}.links.${item.name}.type`) === 'belongsTo') {
                    if (this.getMetadata().get(`entityDefs.${this.entityType}.links.${item.name}.entity`) !== entityType || skip === false) {
                        filteredListLayout.push(item);
                    }
                } else {
                    filteredListLayout.push(item);
                }
            });

            let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.entityType, 'read');
            if (!forbiddenFieldList.length) {
                return filteredListLayout;
            }

            let checkedViaAclListLayout = [];
            filteredListLayout.forEach(item => {
                if (item.name && forbiddenFieldList.indexOf(item.name) < 0) {
                    checkedViaAclListLayout.push(item);
                }
            });

            return checkedViaAclListLayout;
        },

        _loadListLayout: function (callback) {
            this.layoutLoadCallbackList.push(callback);

            if (this.layoutIsBeingLoaded) return;

            this.layoutIsBeingLoaded = true;

            var layoutName = this.layoutName;

            this._helper.layoutManager.get(this.collection.name, layoutName, this.options.layoutRelatedScope ?? null, null, function (data) {
                this.layoutData = data
                var filteredListLayout = this.filterListLayout(data.layout);
                this.layoutLoadCallbackList.forEach(function (callbackItem) {
                    callbackItem(filteredListLayout);
                    this.layoutLoadCallbackList = [];
                    this.layoutIsBeingLoaded = false;
                }, this);
            }.bind(this));
        },

        putAttributesToSelect() {
            let attributesIds = [];
            (this.listLayout || []).forEach(item => {
                if (item.attributeId && !attributesIds.includes(item.attributeId)) {
                    attributesIds.push(item.attributeId);
                }
            })

            if (attributesIds.length > 0) {
                this.collection.data.attributes = attributesIds.join(',');
            }
        },

        getSelectAttributeList: function (callback) {
            if (this.scope == null || this.rowHasOwnLayout) {
                callback(null);
                return;
            }

            if (this.listLayout) {
                var attributeList = this.fetchAttributeListFromLayout();
                this.putAttributesToSelect();
                callback(attributeList);
                return;
            } else {
                this._loadListLayout(function (listLayout) {
                    this.listLayout = listLayout;
                    var attributeList = this.fetchAttributeListFromLayout();
                    this.putAttributesToSelect();
                    callback(attributeList);
                }.bind(this));
                return;
            }
        },

        fetchAttributeListFromLayout: function () {
            let list = [];
            this.listLayout.forEach(function (item) {
                if (!item.name) return;
                const field = item.name;
                const fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                if (!fieldType) return;
                this.getFieldManager().getAttributeList(fieldType, field).forEach(function (attribute) {
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
                }, this);
            }, this);

            let selectList = [];
            if (this.scope && !this.getMetadata().get(['clientDefs', this.scope, 'disabledSelectList'])) {
                selectList = list;
                selectList = this.modifyAttributeList(selectList);
            }

            return selectList;
        },

        modifyAttributeList(attributeList) {
            return _.union(attributeList, this.getMetadata().get(['clientDefs', this.scope, 'additionalSelectAttributes']));
        },

        _getHeaderDefs: function () {
            var defs = [];

            for (var i in this.listLayout) {
                var width = false;

                if ('width' in this.listLayout[i] && this.listLayout[i].width !== null) {
                    width = this.listLayout[i].width + '%';
                } else if ('widthPx' in this.listLayout[i]) {
                    width = this.listLayout[i].widthPx;
                }

                var item = {
                    name: this.listLayout[i].name,
                    sortable: !(this.listLayout[i].notSortable || false) && !this.options.disableSorting,
                    width: width,
                    align: ('align' in this.listLayout[i]) ? this.listLayout[i].align : false,
                };

                let fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', item.name, 'type']);
                if (this.getMetadata().get(['fields', fieldType, 'notSortable'])) {
                    item.sortable = false;
                }

                if ('customLabel' in this.listLayout[i]) {
                    item.customLabel = this.listLayout[i].customLabel;
                    item.hasCustomLabel = true;
                }

                if (this.isRelationField(item.name)) {
                    const name = item.name.split('__')[1]
                    item.label = this.translate(name, 'fields', this.relationScope) + ' (Relation)'
                } else {
                    item.label = this.translate(item.name, 'fields', this.collection.name)
                }


                if (item.sortable) {
                    item.sorted = this.collection.sortBy === this.listLayout[i].name;
                    if (item.sorted) {
                        item.asc = this.collection.asc;
                    }
                }
                defs.push(item);
            }
            ;
            if (this.rowActionsView && !this.rowActionsDisabled) {
                defs.push({
                    width: this.rowActionsColumnWidth,
                    layoutEditor: this.hasLayoutEditor
                });
            }

            const urlParts = (this.collection.url || '').split('/');
            const hasDragDrop = !!(this.getMetadata().get(['clientDefs', urlParts.shift(), 'relationshipPanels', urlParts.pop(), 'dragDrop']));

            let model = this.collection.model.prototype;
            defs.forEach(item => {
                if (hasDragDrop || item.name && ['wysiwyg', 'wysiwygMultiLang'].includes(model.getFieldType(item.name))) {
                    item.sortable = false;
                }
            });

            return defs;
        },

        _convertLayout: function (listLayout, model) {
            model = model || this.collection.model.prototype;

            var layout = [];

            if (this.checkboxes) {
                layout.push({
                    name: 'r-checkboxField',
                    columnName: 'r-checkbox',
                    template: 'record/list-checkbox'
                });
            }

            let hasLink = false;

            for (var i in listLayout) {
                var col = listLayout[i];

                if (!col.name) {
                    continue;
                }
                let item;

                // put defs to model if it's attribute
                if (col.attributeDefs) {
                    model.defs['fields'][col.attributeDefs.name] = col.attributeDefs;
                    if (col.attributeDefs.layoutDetailView) {
                        model.defs['fields'][col.attributeDefs.name]['view'] = col.attributeDefs.layoutDetailView;
                    }
                }

                if (this.isRelationField(col.name)) {
                    const name = col.name.split('__')[1]
                    const type = col.type || this.getMetadata().get(['entityDefs', this.relationScope, 'fields', name, 'type']) || 'base';
                    item = {
                        columnName: col.name,
                        name: col.name + 'Field',
                        view: col.view || model.getFieldParam(col.name, 'view') || this.getMetadata().get(['entityDefs', this.relationScope, 'fields', name, 'view']) || this.getFieldManager().getViewName(type),
                        options: {
                            useRelationModel: true,
                            defs: {
                                name: name,
                                params: col.params || {}
                            },
                            mode: 'list'
                        }
                    }
                } else {
                    const type = col.type || model.getFieldType(col.name) || 'base';
                    item = {
                        columnName: col.name,
                        name: col.name + 'Field',
                        view: col.view || model.getFieldParam(col.name, 'view') || this.getFieldManager().getViewName(type),
                        options: {
                            defs: {
                                name: col.name,
                                params: col.params || {}
                            },
                            mode: 'list'
                        }
                    };
                }

                if (col.width) {
                    item.options.defs.width = col.width;
                }
                if (col.widthPx) {
                    item.options.defs.widthPx = col.widthPx;
                }

                if (col.link) {
                    hasLink = true;
                    item.options.mode = 'listLink';
                    item.options.statusIconsCallback = this.getStatusIcons;
                }
                if (col.align) {
                    item.options.defs.align = col.align;
                }
                layout.push(item);
            }

            if (!hasLink && layout[this.checkboxes ? 1 : 0]) {
                layout[this.checkboxes ? 1 : 0].options.statusIconsCallback = this.getStatusIcons;
            }

            if (this.rowActionsView && !this.rowActionsDisabled) {
                layout.push(this.getRowActionsDefs());
            }

            return layout;
        },

        checkRecord: function (id, $target, isSilent) {
            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');

            if (!$target.size()) return;

            $target.get(0).checked = true;

            var index = this.checkedList.indexOf(id);
            if (index == -1) {
                this.checkedList.push(id);
            }

            $target.closest('tr').addClass('active');

            this.handleAfterCheck(isSilent);
        },

        uncheckRecord: function (id, $target, isSilent) {
            $target = $target || this.$el.find('.record-checkbox[data-id="' + id + '"]');
            if ($target.get(0)) {
                $target.get(0).checked = false;
            }

            var index = this.checkedList.indexOf(id);
            if (index != -1) {
                this.checkedList.splice(index, 1);
            }

            if ($target.get(0)) {
                $target.closest('tr').removeClass('active');
            }

            this.handleAfterCheck(isSilent);
        },

        handleAfterCheck: function (isSilent) {
            if (this.checkedList.length) {
                this.$el.find('.actions-button').removeAttr('disabled');
                this.$el.find('.selected-count').removeClass('hidden');
            } else {
                this.$el.find('.actions-button').attr('disabled', true);
                this.$el.find('.selected-count').addClass('hidden');
            }

            this.$el.find('.selected-count > .selected-count-span').text(this.checkedList.length);

            if (this.checkedList.length == this.collection.models.length) {
                this.$el.find('.select-all').prop('checked', true);
            } else {
                this.$el.find('.select-all').prop('checked', false);
            }

            if (!isSilent) {
                this.trigger('check');
            }
        },

        getRowActionsDefs: function () {
            return {
                columnName: 'buttons',
                name: 'buttonsField',
                view: this.rowActionsView,
                options: {
                    defs: {
                        params: {}
                    }
                }
            };
        }, /**
         * Returns checked models.
         * @return {Array} Array of models
         */
        getSelected: function () {
            var list = [];
            this.$el.find('input.record-checkbox:checked').each(function (i, el) {
                var id = $(el).data('id');
                var model = this.collection.get(id);
                list.push(model);
            }.bind(this));
            return list;
        },

        getInternalLayoutForModel: function (callback, model) {
            var scope = model.name;
            if (this._internalLayout == null) {
                this._internalLayout = {};
            }
            if (!(scope in this._internalLayout)) {
                this._internalLayout[scope] = this._convertLayout(this.listLayout[scope], model);
            }
            callback(this._internalLayout[scope]);
        },

        getInternalLayout: function (callback, model) {
            if (this.scope == null || this.rowHasOwnLayout) {
                if (!model) {
                    callback(null);
                    return;
                } else {
                    this.getInternalLayoutForModel(callback, model);
                    return;
                }
            }
            if (this._internalLayout !== null) {
                callback(this._internalLayout);
                return;
            }
            if (this.listLayout !== null) {
                this._internalLayout = this._convertLayout(this.listLayout);
                callback(this._internalLayout);
                return;
            }
            this._loadListLayout(function (listLayout) {
                this.listLayout = listLayout;
                this._internalLayout = this._convertLayout(listLayout);
                callback(this._internalLayout);
                return;
            }.bind(this));
        },

        getItemEl: function (model, item) {
            return this.options.el + ' tr[data-id="' + model.id + '"] td.cell[data-name="' + item.columnName + '"]';
        },

        prepareInternalLayout: function (internalLayout, model) {
            internalLayout.forEach(function (item) {
                item.el = this.getItemEl(model, item);
            }, this);
        },

        getRelationScope() {
            const entityType = (this.options.layoutRelatedScope ?? '').split('.')[0]
            if (entityType) {
                return Espo.utils.upperCaseFirst(this.getMetadata().get(['entityDefs', entityType, 'links', this.relationName, 'relationName']))
            }
            return null
        },

        isRelationField(name) {
            if (!name) return false
            return name.split('__').length === 2
        },

        buildRow: function (i, model, callback) {
            var key = model.id;

            this.rowList.push(key);

            this.getInternalLayout(function (internalLayout) {
                internalLayout = Espo.Utils.cloneDeep(internalLayout);
                this.prepareInternalLayout(internalLayout, model);

                const entityDisabled = this.getMetadata().get(['scopes', model.name, 'disabled'])
                var acl = {
                    edit: entityDisabled ? false : this.getAcl().checkModel(model, 'edit'),
                    delete: entityDisabled ? false : this.getAcl().checkModel(model, 'delete'),
                    unlink: this.options.canUnlink
                };

                let getRelationModel = (callback) => {
                    if (model.get('__relationEntity')) {
                        this.getModelFactory().create(this.relationScope, relModel => {
                            relModel.set(model.get('__relationEntity'));
                            model.relationModel = relModel
                            callback(relModel)
                        })
                    } else {
                        callback(null)
                    }
                }

                getRelationModel((relModel) => {
                    this.createView(key, 'views/base', {
                        model: model,
                        relationModel: relModel,
                        acl: acl,
                        el: this.options.el + ' .list-row[data-id="' + key + '"]',
                        optionsToPass: ['acl', 'scope'],
                        scope: this.scope,
                        noCache: true,
                        _layout: {
                            type: this._internalLayoutType,
                            layout: internalLayout
                        },
                        name: this.type + '-' + model.name,
                        setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered()
                    }, callback);
                })


            }.bind(this), model);
        },

        createLayoutConfigurator() {
            $(this.getSelector() + ' .layout-editor-container').each((idx, el) => {
                this.createView('layoutConfigurator' + idx, "views/record/layout-configurator", {
                    scope: this.scope,
                    viewType: this.layoutName,
                    relatedScope: this.options.layoutRelatedScope,
                    layoutData: this.layoutData,
                    el: el,
                    alignRight: true
                }, (view) => {
                    view.on("refresh", () => {
                        if (this.options.disableRefreshLayout) {
                            this.trigger('refresh-layout')
                        } else {
                            this.refreshLayout()
                        }
                    })
                    view.render()
                })
            })
        },

        buildRows: function (callback) {
            this.rowList = [];


            if (this.collection.length > 0) {
                var i = 0;
                var c = !this.pagination ? 1 : 2;
                var func = function () {
                    i++;
                    if (i == c) {
                        if (typeof callback == 'function') {
                            callback();
                        }
                    }
                }

                this.wait(true);

                var modelList = this.collection.models;
                var count = modelList.length;
                var built = 0;
                modelList.forEach(function (model) {
                    this.buildRow(i, model, function (view) {
                        this.listenToOnce(view, 'after:render', () => {
                            if (!view.$el) {
                                return;
                            }

                            let el = view.$el.find('a.link[data-id]');
                            if (el.size() === 0) {
                                el = view.$el.find('td:not([data-name=draggableIcon]):not([data-name=r-checkbox]):first-child > *');
                            }

                            el?.parent().find('.icons-container').remove();
                            const icons = $('<sup class="status-icons icons-container"></sup>');
                            (this.getStatusIcons(view.model) || []).forEach(el => icons.append(el));
                            this.afterRenderStatusIcons(icons, view.model);
                            el?.parent().append('&nbsp;');
                            el?.parent().append(icons);
                        })

                        built++;
                        if (built == count) {
                            func();
                            this.wait(false);
                            this.trigger('after:build-rows');
                        }
                    }.bind(this));
                }, this);


                if (this.pagination) {
                    this.createView('pagination', 'views/record/list-pagination', {
                        collection: this.collection
                    }, func);
                }
            } else {
                if (typeof callback == 'function') {
                    callback();
                    this.trigger('after:build-rows');
                }
            }
        },

        afterRenderStatusIcons(icons, model) {
            // do something
        },

        showMoreRecords: function (collection, $list, $showMore, callback) {
            collection = collection || this.collection;

            $showMore = $showMore || this.$el.find('.show-more');
            $list = $list || this.$el.find(this.listContainerEl);

            $showMore.children('a').addClass('disabled');

            Espo.Ui.notify(this.translate('loading', 'messages'));

            var final = function () {
                $showMore.parent().append($showMore);
                if (
                    (collection.total > collection.length + collection.lengthCorrection || collection.total == -1)
                ) {
                    $showMore.find('span.more-label').text(this.getShowMoreLabel());
                    $showMore.removeClass('hidden');
                }
                $showMore.children('a').removeClass('disabled');

                if (this.allResultIsChecked) {
                    this.$el.find('input.record-checkbox').attr('disabled', 'disabled').prop('checked', true);
                }

                Espo.Ui.notify(false);

                if (callback) {
                    callback.call(this);
                }
            }.bind(this);

            var initialCount = collection.length;

            var success = function () {
                Espo.Ui.notify(false);
                $showMore.addClass('hidden');

                var rowCount = collection.length - initialCount;
                var rowsReady = 0;
                if (collection.length <= initialCount) {
                    final();
                }
                for (var i = initialCount; i < collection.length; i++) {
                    var model = collection.at(i);
                    this.buildRow(i, model, function (view) {
                        var model = view.model;
                        view.getHtml(function (html) {
                            var $row = $(this.getRowContainerHtml(model.id));
                            $row.append(html);
                            $list.append($row);
                            rowsReady++;
                            if (rowsReady == rowCount) {
                                final();
                            }
                            view._afterRender();
                            if (view.options.el) {
                                view.setElement(view.options.el);
                            }
                        }.bind(this));
                    });
                }
                this.noRebuild = true;
            }.bind(this);

            this.listenToOnce(collection, 'update', function (collection, o) {
                if (o.changes.merged.length) {
                    collection.lengthCorrection += o.changes.merged.length;
                }
            }, this);

            collection.fetch({
                success: success,
                remove: false,
                more: true
            });
        },

        getRowContainerHtml: function (id) {
            return '<tr data-id="' + id + '" class="list-row"></tr>';
        },

        getStatusIcons: function (model) {
            const htmlIcons = [];

            if (model.get('isInherited')) {
                htmlIcons.push(`<i class="ph ph-link-simple-horizontal" title="${this.translate('inherited')}"></i>`);
            }

            return htmlIcons;
        },

        actionQuickView: function (data) {
            data = data || {};
            var id = data.id;
            if (!id) return;

            var model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }

            model.defs['_relationName'] = this.relationName;

            var scope = data.scope || model.name || this.scope;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.detail') || 'views/modals/detail';

            if (this.options.openInTab) {
                window.open(`/#${scope}/view/${id}`, "_blank");
                return
            }

            if (!this.quickDetailDisabled) {
                Espo.Ui.notify(this.translate('loading', 'messages'));

                var options = {
                    scope: scope,
                    layoutRelatedScope: this.options.layoutRelatedScope,
                    model: model,
                    id: id,
                    htmlStatusIcons: this.getStatusIcons(model) || []
                };
                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }
                this.createView('modal', viewName, options, function (view) {
                    this.listenToOnce(view, 'after:render', function () {
                        Espo.Ui.notify(false);
                    });
                    view.render();

                    this.listenToOnce(view, 'remove', function () {
                        this.clearView('modal');
                    }, this);

                    this.listenToOnce(view, 'after:edit-cancel', function () {
                        this.actionQuickView({ id: view.model.id, scope: view.model.name });
                    }, this);

                    this.listenToOnce(view, 'after:save', function (model) {
                        this.trigger('after:save', model);
                    }, this);
                }, this);
            } else {
                this.getRouter().navigate('#' + scope + '/view/' + id, { trigger: true });
            }
        },

        actionOpenInTab: function (data) {
            window.open(data.url, "_blank");
        },

        actionReupload: function (data) {
            if (!data.id || !this.collection) {
                this.notify('Wrong input data', 'error');
            }

            let model = this.collection.get(data.id);

            this.notify('Loading...');
            this.createView('upload', 'views/file/modals/upload', {
                scope: 'File',
                fullFormDisabled: true,
                layoutName: 'upload',
                multiUpload: false,
                attributes: _.extend(model.attributes, { reupload: model.id }),
            }, view => {
                view.render();
                this.notify(false);
                this.listenTo(view.model, 'after:file-upload', entity => {
                    model.trigger('reuploaded');
                });
                this.listenToOnce(view, 'close', () => {
                    this.clearView('upload');
                });
            });
        },

        actionQuickEdit: function (data) {
            data = data || {}
            var id = data.id;
            if (!id) return;

            var model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }

            model.defs['_relationName'] = this.relationName;

            if (this.options.useRelationModelOnEdit){
                model = model.relationModel
            }

            var scope = data.scope || model.name || this.scope;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            if (this.options.openInTab) {
                window.open(`/#${scope}/edit/${id}`, "_blank");
                return
            }

            if (!this.quickEditDisabled) {
                Espo.Ui.notify(this.translate('loading', 'messages'));
                var options = {
                    scope: scope,
                    layoutRelatedScope: this.options.layoutRelatedScope,
                    id: id,
                    model: model,
                    fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
                    htmlStatusIcons: this.getStatusIcons(model) || [],
                    returnUrl: this.getRouter().getCurrentUrl(),
                    returnDispatchParams: {
                        controller: scope,
                        action: null,
                        options: {
                            isReturn: true
                        }
                    }
                };
                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }
                this.createView('modal', viewName, options, function (view) {
                    view.once('after:render', function () {
                        Espo.Ui.notify(false);
                    });

                    view.render();

                    this.listenToOnce(view, 'remove', function () {
                        this.clearView('modal');
                    }, this);

                    this.listenToOnce(view, 'after:save', function (m) {
                        var model = this.collection.get(m.id);
                        if (model) {
                            model.set(m.getClonedAttributes());
                        }

                        this.trigger('after:save', m);
                    }, this);
                }, this);
            } else {
                var options = {
                    id: id,
                    model: this.collection.get(id),
                    returnUrl: this.getRouter().getCurrentUrl(),
                    returnDispatchParams: {
                        controller: scope,
                        action: null,
                        options: {
                            isReturn: true
                        }
                    }
                };
                if (this.options.keepCurrentRootUrl) {
                    options.rootUrl = this.getRouter().getCurrentUrl();
                }
                this.getRouter().navigate('#' + scope + '/edit/' + id, { trigger: false });
                this.getRouter().dispatch(scope, 'edit', options);
            }
        },

        actionDynamicAction: function (data) {
            const defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).find(defs => defs.id === data.action_id)
            if (defs && defs.type) {
                const method = 'actionDynamicAction' + Espo.Utils.upperCaseFirst(defs.type);
                if (typeof this[method] == 'function') {
                    this[method].call(this, data);
                    return
                }
            }

            this.executeActionRequest({
                actionId: data.action_id,
                entityId: data.entity_id
            })
        },

        executeActionRequest: function (payload, callback) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow?silent=true', payload).success(response => {
                if (response.inBackground) {
                    this.notify(this.translate('jobAdded', 'messages'), 'success');

                    if (callback) {
                        callback();
                    }
                } else {
                    if (response.success) {
                        this.notify(response.message, 'success');
                        if (response.redirect) {
                            this.getRouter().navigate('#' + response.scope + '/view/' + response.entityId, { trigger: false });
                            this.getRouter().dispatch(response.scope, 'view', {
                                id: response.entityId,
                            })
                            return;
                        }
                        if (callback) {
                            callback();
                        }
                    } else {
                        this.notify(response.message, 'error');
                    }
                }
                this.collection.fetch();
            })
                .error(error => {
                    let message = error.responseText
                    if (!message && error.status === 403) {
                        message = this.translate('Action Forbidden', 'labels')
                    }
                    this.notify(message, 'error')
                })
        },

        getRowSelector: function (id) {
            return 'tr[data-id="' + id + '"]';
        },

        actionBookmark: function (data) {
            data = data || {}
            let id = data.entity_id;
            let bookmarkId = data.bookmark_id;
            if (!id) return;
            let model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!model) {
                return;
            }
            model.set('bookmarkId', bookmarkId);
            if (bookmarkId) {
                this.notify(this.translate('Unbookmarking') + '...');
                $.ajax({
                    url: `Bookmark/${bookmarkId}`,
                    type: 'DELETE',
                    headers: {
                        'permanently': true
                    }
                }).done(function (result) {
                    this.notify(this.translate('Done'), 'success')
                    this.trigger('unbookmarked-' + model.urlRoot, model.get('bookmarkId'))
                    model.set('bookmarkId', null)
                    this.reRender()
                    this.collection.fetch()
                }.bind(this));
            } else {
                this.notify(this.translate('Bookmarking') + '...');
                $.ajax({
                    url: 'Bookmark',
                    type: 'POST',
                    data: JSON.stringify({
                        entityType: this.entityType,
                        entityId: model.id
                    })
                }).done(function (result) {

                    model.set('bookmarkId', result.id)
                    this.notify(this.translate('Done'), 'success')
                    this.trigger('bookmarked-' + model.urlRoot, model.get('bookmarkId'))
                    let shouldNotReRender = false;

                    for (const where of (this.collection.where ?? [])) {
                        if (where.type === 'bool' && (where.value ?? []).includes('onlyBookmarked')) {
                            this.collection.fetch()
                            shouldNotReRender = true;
                            break;
                        }
                    }

                    if (!shouldNotReRender) {
                        this.reRender();
                    }

                }.bind(this));
            }
        },

        actionQuickRemove: function (data) {
            data = data || {}
            var id = data.id;
            if (!id) return;

            var model = this.collection.get(id);
            if (!this.getAcl().checkModel(model, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            let message = 'Global.messages.removeRecordConfirmation';
            if (this.isHierarchical()) {
                message = 'Global.messages.removeRecordConfirmationHierarchically';
            }

            let scopeMessage = this.getMetadata().get(`clientDefs.${this.scope}.deleteConfirmation`);
            if (scopeMessage) {
                message = scopeMessage;
            }

            let parts = message.split('.');

            let action = () => {
                this.collection.trigger('model-removing', id);
                this.collection.remove(model);
                this.notify('removing');
                model.destroy({
                    wait: true,
                    success: function () {
                        this.notify('Removed', 'success');
                        this.removeRecordFromList(id);
                    }.bind(this),
                    error: function () {
                        this.notify('Error occured', 'error');
                        this.collection.push(model);
                    }.bind(this)
                });
            }

            if (this.getMetadata().get(['scopes', this.scope, 'deleteWithoutConfirmation'])) {
                action();
                return;
            }

            this.confirm({
                message: (this.translate(parts.pop(), parts.pop(), parts.pop())).replace('{{name}}', model.get('name')),
                confirmText: this.translate('Remove')
            }, function () {
                action();
            }, this);
        },

        actionQuickRestore: function (data) {
            data = data || {}
            var id = data.id;
            if (!id) return;

            var model = this.collection.get(id);
            if (!this.getAcl().checkModel(model, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            let message = 'Global.messages.restoreRecordConfirmation';

            let scopeMessage = this.getMetadata().get(`clientDefs.${this.scope}.restoreConfirmation`);
            if (scopeMessage) {
                message = scopeMessage;
            }

            let parts = message.split('.');

            this.confirm({
                message: (this.translate(parts.pop(), parts.pop(), parts.pop())).replace('{{name}}', model.get('name')),
                confirmText: this.translate('Restore')
            }, function () {
                this.collection.trigger('model-removing', id);
                this.collection.remove(model);
                this.notify('restoring');
                $.ajax({
                    url: this.entityType + '/action/restore',
                    type: 'POST',
                    data: JSON.stringify({ id: id })
                }).done(function (result) {
                        this.notify('Restored', 'success');
                        this.removeRecordFromList(id);
                    }.bind(this)
                ).fail(function () {
                    this.notify('Error occured', 'error');
                    this.collection.push(model);
                }.bind(this))
            }, this);
        },

        actionDeletePermanently(data) {
            let id = (data || { id: null }).id;
            if (!id) {
                return;
            }

            let model = this.collection.get(id);
            if (!this.getAcl().checkModel(model, 'delete')) {
                this.notify('Access denied', 'error');
                return;
            }

            this.confirm({
                message: this.translate('deletePermanentlyRecordConfirmation', 'messages'),
                confirmText: this.translate('Delete')
            }, () => {
                this.collection.trigger('model-removing', id);
                this.collection.remove(model);
                this.notify('removing');
                $.ajax({
                    url: `${this.entityType}/${id}`,
                    type: 'DELETE',
                    headers: {
                        permanently: 'true'
                    }
                }).done(result => {
                    this.notify('Removed', 'success');
                    this.removeRecordFromList(id);
                }).fail(() => {
                    this.notify('Error occured', 'error');
                    this.collection.push(model);
                });
            });
        },

        removeRecordFromList: function (id) {
            this.collection.remove(id);
            this.$el.find('.total-count-span').text(this.collection.total.toString());

            var index = this.checkedList.indexOf(id);
            if (index != -1) {
                this.checkedList.splice(index, 1);
            }

            this.removeRowHtml(id);
            var key = id;
            this.clearView(key);
            var index = this.rowList.indexOf(key);
            if (~index) {
                this.rowList.splice(index, 1);
            }
        },

        removeRowHtml: function (id) {
            this.$el.find(this.getRowSelector(id)).remove();
            if (this.collection.length == 0 && (this.collection.total == 0 || this.collection.total === -2)) {
                this.reRender();
            }
        },

        actionQuickCompare: function (data) {
            data = data || {}
            var id = data.id;
            if (!id) return;
            var model = null;
            if (this.collection) {
                model = this.collection.get(id);
            }
            if (!data.scope && !model) {
                return;
            }
            if (!this.getAcl().check(data.scope, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }
            this.notify('Loading...');

            this.getModelFactory().create(data.scope, function (model) {
                model.id = data.id;
                this.listenToOnce(model, 'sync', function () {
                    let view = this.getMetadata().get(['clientDefs', data.scope, 'modalViews', 'compare']) || 'views/modals/compare'
                    this.createView('recordCompareInstance', view, {
                        model: model,
                        scope: data.scope,
                        instanceComparison: true,
                        mode: "details",
                    }, function (dialog) {
                        dialog.render();
                        this.notify(false)
                    });
                }, this);
                model.fetch({ main: true });
            }, this);
        },
    });
});
