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
         * @param {String} Type of the list. Can be 'list', 'listSmall'.
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

                model.fetch().success(() => {
                    var options = {
                        id: id,
                        model: model
                    };
                    if (this.options.keepCurrentRootUrl) {
                        options.rootUrl = this.getRouter().getCurrentUrl();
                    }

                    this.getRouter().navigate('#' + scope + '/view/' + id, {trigger: false});
                    this.getRouter().dispatch(scope, 'view', options);
                });
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
                if (this.allResultIsChecked) {
                    this.unselectAllResult();
                } else {
                    this.selectAllResult();
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
                // this.listenTo(this.collection, 'listSorted', () => this.collection.fetch());
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
                this.trigger('sort', {sortBy: field, asc: asc});
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

        massActionList: ['remove', 'merge', 'massUpdate', 'export'],

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
                massActionList: this.massActionList,
                rowList: this.rowList,
                topBar: paginationTop || this.checkboxes || (this.buttonList.length && !this.buttonsDisabled) || fixedHeaderRow,
                bottomBar: paginationBottom,
                buttonList: this.buttonList,
                displayTotalCount: this.displayTotalCount && this.collection.total >= 0,
                countLabel: this.getShowMoreLabel(),
                showNoData: !this.collection.total && !fixedHeaderRow
            };
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

            this.trigger('select-all-results');
        },

        unselectAllResult: function () {
            this.allResultIsChecked = false;

            this.$el.find('input.record-checkbox').prop('checked', false).removeAttr('disabled');
            this.$el.find('input.select-all').prop('checked', false);


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
                        if ('count' in result) {
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

        massActionDynamicMassAction: function (data) {
            let where;
            if (this.allResultIsChecked) {
                where = this.collection.getWhere();
            } else {
                where = [{type: "in", attribute: "id", value: this.checkedList}];
            }

            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow', {
                actionId: data.id,
                where: where,
                massAction: true
            }).success(response => {
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

        massActionRemove: function () {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                this.notify('Access denied', 'error');
                return false;
            }

            this.confirm({
                message: this.prepareRemoveSelectedRecordsConfirmationMessage(),
                confirmText: this.translate('Remove')
            }, function () {
                this.notify(this.translate('removing', 'labels', 'Global'));

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
                    url: this.entityType + '/action/massDelete',
                    type: 'POST',
                    data: JSON.stringify(data)
                }).done(function (result) {
                    this.notify(false)
                    this.collection.fetch();
                }.bind(this));
            }, this);
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
                    this.collection.fetch();
                }.bind(this));
            }, this);
        },

        prepareRemoveSelectedRecordsConfirmationMessage: function () {
            let scopeMessage = this.getMetadata()
                .get(`clientDefs.${this.scope}.removeSelectedRecordsConfirmation`)
                ?.split('.');
            let message = this.translate('removeSelectedRecordsConfirmation', 'messages');
            if (scopeMessage?.length > 0) {
                message = this.translate(scopeMessage.pop(), scopeMessage.pop(), scopeMessage.pop());
                var selectedIds = this.checkedList;
                var selectedNames = this.collection.models
                    .filter(function (model) {
                        return selectedIds.includes(model.id);
                    })
                    .map(function (model) {
                        return "'"+model.attributes['name']+"'";
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
                        return "'"+model.attributes['name']+"'";
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

        massActionMerge: function () {
            if (!this.getAcl().check(this.entityType, 'edit')) {
                this.notify('Access denied', 'error');
                return false;
            }

            if (this.checkedList.length < 2) {
                this.notify('Select 2 or more records', 'error');
                return;
            }
            if (this.checkedList.length > 4) {
                this.notify('Select not more than 4 records', 'error');
                return;
            }
            this.checkedList.sort();
            var url = '#' + this.entityType + '/merge/ids=' + this.checkedList.join(',');
            this.getRouter().navigate(url, {trigger: false});
            this.getRouter().dispatch(this.entityType, 'merge', {
                ids: this.checkedList.join(','),
                collection: this.collection
            });
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
                view.once('after:update', function () {
                    view.close();
                    this.listenToOnce(this.collection, 'sync', function () {
                        Espo.Ui.success(this.translate('Done'));
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

            (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).forEach(dynamicAction => {
                if (this.getAcl().check(dynamicAction.acl.scope, dynamicAction.acl.action) && dynamicAction.massAction) {
                    let obj = {
                        action: "dynamicMassAction",
                        label: dynamicAction.name,
                        id: dynamicAction.id
                    };
                    this.massActionList.push(obj);
                    this.checkAllResultMassActionList.push(obj);
                }
            });

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

            if (this.options.massActionsDisabled) {
                this.massActionList = [];
            }

            if (!this.massActionList.length && !this.selectable) {
                this.checkboxes = false;
            }

            this.listenTo(this.collection, 'sync', function (c, r, options) {
                if (this.hasView('modal') && this.getView('modal').isRendered()) return;
                if (this.noRebuild) {
                    this.noRebuild = null;
                    return;
                }
                this.checkedList = [];
                this.allResultIsChecked = false;
                this.buildRows(function () {
                    this.render();
                }.bind(this));
            }, this);

            this.checkedList = [];
            if (!this.options.skipBuildRows) {
                this.buildRows();
            }

            this.enabledFixedHeader = this.options.enabledFixedHeader || this.enabledFixedHeader;
            this.baseWidth = [];

            this.listenTo(this, 'after:save', () => {
                this.afterSave();
            });

            this.listenTo(this.collection, 'sync', () => {
                let $shown = this.$el.find('.shown-count-span');
                if ($shown.length > 0) {
                    $shown.html(this.collection.length);
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
        },

        afterSave: function () {
            this.collection.fetch();
        },

        afterRender: function () {

            if (this.allResultIsChecked) {
                this.selectAllResult();
            } else {
                if (this.checkedList.length) {
                    this.checkedList.forEach(function (id) {
                        this.checkRecord(id);
                    }, this);
                }
            }

            let list = $('#main > .list-container > .list');
            if (!list) {
                return;
            }

            var $bar = $('<div class="fixed-scrollbar" style="display: none"><div></div></div>').appendTo(list).css({
                width: list.outerWidth()
            });
            $bar.scroll(function () {
                list.scrollLeft($bar.scrollLeft());
            });
            $bar.data("status", "off");

            var fixSize = function () {
                var $container = $bar.parent();

                if ($container.length) {
                    $bar.children('div').height(1).width($container[0].scrollWidth);
                    $bar.width($container.width()).scrollLeft($container.scrollLeft());
                }
            };

            this.fullTableScroll();
            $(window).on("resize.fixed-scrollbar tree-width-changed tree-width-unset", function () {
                this.fullTableScroll();

                if (list) {
                    if (!this.hasHorizontalScroll() || $(window).width() < 768) {
                        $('.fixed-scrollbar').css('display', 'none');
                    } else {
                        $('.fixed-scrollbar').css('display', 'block');
                        $('td[data-name="buttons"]').addClass('fixed-button');
                        fixSize();
                    }
                }
            }.bind(this));

            if (this.enabledFixedHeader) {
                this.fixedTableHead()
            }

            if (this.hasHorizontalScroll()) {
                fixSize();

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
                                    if ($bar.data("status") == "on") {
                                        $bar.hide().data("status", "off");
                                    }
                                } else {
                                    if ($bar.data("status") == "off") {
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
                    } else if (this.$el.parent().prop('id') === 'main') {
                        $(window).off('scroll', this.$el);
                        $(window).on('scroll', this.$el, function () {
                            if (this.collection.total > this.collection.length + this.collection.lengthCorrection && $(window).scrollTop() + $(window).height() >= $(document).height() - 50) {
                                this.loadMore(this.$el.find('a[data-action="showMore"]'));
                            }
                        }.bind(this));
                    }
                }.bind(this), 50)
            }
            const filters = this.getStorage().get('listSearch', this.scope);
            if(filters && filters.bool['onlyDeleted'] === true && !this.massActionList.includes('restore')){
                this.massActionListBackup = this.massActionList;
                this.checkAllResultMassActionListBackup = this.checkAllResultMassActionList;
                this.massActionList = ['restore'];
                this.checkAllResultMassActionList = ['restore'];this.reRender();
            }

            if(filters && filters.bool['onlyDeleted'] !== true && this.massActionList.includes('restore')){
                this.massActionList = this.massActionListBackup;
                this.checkAllResultMassActionList = this.checkAllResultMassActionListBackup
                this.reRender()
            }
        },

        isHierarchical() {

            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy'
                && this.getMetadata().get(`scopes.${this.scope}.disableHierarchy`) !== true ;
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

        changeDropDownPosition() {
            let el = this.$el;
            el.on('show.bs.dropdown', function (e) {
                let target = e.relatedTarget;
                let menu = $(target).siblings('.dropdown-menu');
                if (target && menu) {
                    let menuHeight = menu.height();
                    let positionTop = $(target).offset().top + $(target).outerHeight(true);

                    if ((positionTop + menuHeight) > this.getHeightParentPosition()) {
                        menu.css({
                            'top': `-${menuHeight}px`
                        });
                    }
                }
            }.bind(this));

            el.on('hide.bs.dropdown', function (e) {
                $(e.relatedTarget).next('.dropdown-menu').removeAttr('style');
            });
        },

        initDraggableList() {
            if (this.getAcl().check(this.scope, 'edit')) {
                this.setCellWidth();

                this.$el.find(this.listContainerEl).sortable({
                    handle: window.innerWidth < 768 ? '.cell[data-name="draggableIcon"]' : false,
                    delay: 150,
                    update: function (e, ui) {
                        this.saveListItemOrder(e, ui);
                    }.bind(this)
                });
            }
        },

        setCellWidth() {
            let el = this.$el.find(this.listContainerEl);

            el.find('td').each(function (i) {
                $(this).css('width', $(this).outerWidth());
            });
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
                fixedTable = this.$el.find('.fixed-header-table'),
                fullTable = this.$el.find('.full-table'),
                navBarRight = $('.navbar-right'),
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
                        'z-index': 1
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

                $window.on('scroll', toggleClass);
                $window.on('resize tree-width-changed tree-width-unset', function () {
                    this.fullTableScroll();
                    setPosition();
                    setWidth();
                }.bind(this));
            }
        },

        fullTableScroll() {
            let list = this.$el.find('.list');
            if (list.length) {
                let fixedTableHeader = list.find('.fixed-header-table');
                let fullTable = list.find('.full-table');
                let scroll = this.getParentView().$el.siblings('.panel-scroll');

                if (fullTable.length) {
                    if (scroll.length) {
                        scroll.scrollLeft(0);
                        scroll.addClass('hidden');
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

                    let rowsButtons = this.$el.find('td[data-name="buttons"]');
                    if ($(window).outerWidth() > 768 && rowsButtons.length) {
                        rowsButtons.addClass('fixed-button');
                        rowsButtons.each(function () {
                            $(this).css('left', list.width() - $(this).width() - 5)
                        });
                    }

                    let prevScrollLeft = 0;

                    list.off('scroll');
                    list.on('scroll', () => {
                        if (prevScrollLeft !== list.scrollLeft()) {
                            let fixedTableHeaderBasePosition = list.offset().left + 1 || 0;
                            fixedTableHeader.css('left', fixedTableHeaderBasePosition - list.scrollLeft());

                            if ($(window).outerWidth() > 768 && rowsButtons.hasClass('fixed-button')) {
                                rowsButtons.each(function () {
                                    $(this).css('left', list.scrollLeft() + list.width() - $(this).width() - 5)
                                });
                            }
                        }
                        prevScrollLeft = list.scrollLeft();
                    });

                    if (this.hasHorizontalScroll()) {

                        // custom scroll for relationship panels
                        if (scroll.length) {
                            scroll.removeClass('hidden');

                            scroll.css({width: list.width(), display: 'block'});
                            scroll.find('div').css('width', fullTable.width());
                            rowsButtons.each(function () {
                                $(this).css('left', scroll.scrollLeft() + list.width() - $(this).width() - 5)
                            });

                            this.listenTo(this.collection, 'sync', function () {
                                if (!this.hasHorizontalScroll()) {
                                    scroll.addClass('hidden');
                                }
                            }.bind(this));

                            scroll.on('scroll', () => {
                                fullTable.css('left', -1 * scroll.scrollLeft());
                                rowsButtons.each(function () {
                                    $(this).css('left', scroll.scrollLeft() + list.width() - $(this).width() - 5)
                                });
                            });

                            if ($(window).width() < 768) {
                                let touchStartPosition = 0,
                                    touchFinalPosition = 0,
                                    currentScroll = 0;

                                list.on('touchstart', function (e) {
                                    touchStartPosition = e.originalEvent.targetTouches[0].pageX;
                                    currentScroll = scroll.scrollLeft();
                                }.bind(this));

                                list.on('touchmove', function (e) {
                                    touchFinalPosition = e.originalEvent.targetTouches[0].pageX;

                                    scroll.scrollLeft(currentScroll - (touchFinalPosition - touchStartPosition));
                                }.bind(this));
                            }
                        }
                    }
                }
            }
        },

        setupMassActionItems() {
            if (this.getMetadata().get(['scopes', this.scope, 'addRelationEnabled'])) {
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

                    if (defs.foreign && defs.entity && this.getAcl().check(defs.entity, 'edit')) {
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

            let parentView = this.getParentView();

            let entityType = null;
            let entityId = null;

            if (parentView && parentView.options && parentView.options.model) {
                entityType = parentView.options.model.urlRoot;
                entityId = parentView.options.model.get('id');
            }

            listLayout = Espo.Utils.cloneDeep(listLayout);

            // remove relation virtual fields
            if (this.layoutName === 'listSmall') {
                let toRemove = [];
                listLayout.forEach((item, k) => {
                    let parts = item.name.split('__');
                    if (parts.length === 2) {
                        toRemove.push({number: k, relEntity: parts[0]});
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
                 if (this.layoutName === 'listSmall' && this.getMetadata().get(`entityDefs.${this.entityType}.links.${item.name}.type`) === 'belongsTo') {
                    if (this.getMetadata().get(`entityDefs.${this.entityType}.links.${item.name}.entity`) !== entityType) {
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

            this._helper.layoutManager.get(this.collection.name, layoutName, function (listLayout) {
                var filteredListLayout = this.filterListLayout(listLayout);
                this.layoutLoadCallbackList.forEach(function (callbackItem) {
                    callbackItem(filteredListLayout);
                    this.layoutLoadCallbackList = [];
                    this.layoutIsBeingLoaded = false;
                }, this);
            }.bind(this));
        },

        getSelectAttributeList: function (callback) {
            if (this.scope == null || this.rowHasOwnLayout) {
                callback(null);
                return;
            }

            if (this.listLayout) {
                var attributeList = this.fetchAttributeListFromLayout();
                callback(attributeList);
                return;
            } else {
                this._loadListLayout(function (listLayout) {
                    this.listLayout = listLayout;
                    var attributeList = this.fetchAttributeListFromLayout();
                    callback(attributeList);
                }.bind(this));
                return;
            }
        },

        fetchAttributeListFromLayout: function () {
            let list = [];
            this.listLayout.forEach(function (item) {
                if (!item.name) return;
                var field = item.name;
                var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                if (!fieldType) return;
                this.getFieldManager().getAttributeList(fieldType, field).forEach(function (attribute) {
                    list.push(attribute);
                    if (fieldType === 'linkMultiple' && attribute === field + 'Ids') {
                        let foreignName = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'foreignName']);
                        if (foreignName && foreignName !== 'name') {
                            list.push(field);
                        }
                    }
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
                    sortable: !(this.listLayout[i].notSortable || false),
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
                    width: this.rowActionsColumnWidth
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

            for (var i in listLayout) {
                var col = listLayout[i];
                var type = col.type || model.getFieldType(col.name) || 'base';
                if (!col.name) {
                    continue;
                }

                var item = {
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
                if (col.width) {
                    item.options.defs.width = col.width;
                }
                if (col.widthPx) {
                    item.options.defs.widthPx = col.widthPx;
                }

                if (col.link) {
                    item.options.mode = 'listLink';
                }
                if (col.align) {
                    item.options.defs.align = col.align;
                }
                layout.push(item);
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
            } else {
                this.$el.find('.actions-button').attr('disabled', true);
            }

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

        buildRow: function (i, model, callback) {
            var key = model.id;

            this.rowList.push(key);
            this.getInternalLayout(function (internalLayout) {
                internalLayout = Espo.Utils.cloneDeep(internalLayout);
                this.prepareInternalLayout(internalLayout, model);

                var acl = {
                    edit: this.getAcl().checkModel(model, 'edit'),
                    delete: this.getAcl().checkModel(model, 'delete'),
                    unlink: this.options.canUnlink
                };

                this.createView(key, 'views/base', {
                    model: model,
                    acl: acl,
                    el: this.options.el + ' .list-row[data-id="' + key + '"]',
                    optionsToPass: ['acl','scope'],
                    scope: this.scope,
                    noCache: true,
                    _layout: {
                        type: this._internalLayoutType,
                        layout: internalLayout
                    },
                    name: this.type + '-' + model.name,
                    setViewBeforeCallback: this.options.skipBuildRows && !this.isRendered()
                }, callback);
            }.bind(this), model);
        },

        buildRows: function (callback) {
            this.checkedList = [];
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
                    this.buildRow(i, model, function () {
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

            var scope = data.scope || model.name || this.scope;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.detail') || 'views/modals/detail';

            if (!this.quickDetailDisabled) {
                Espo.Ui.notify(this.translate('loading', 'messages'));

                var options = {
                    scope: scope,
                    model: model,
                    id: id
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
                        this.actionQuickView({id: view.model.id, scope: view.model.name});
                    }, this);

                    this.listenToOnce(view, 'after:save', function (model) {
                        this.trigger('after:save', model);
                    }, this);
                }, this);
            } else {
                this.getRouter().navigate('#' + scope + '/view/' + id, {trigger: true});
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
                attributes: _.extend(model.attributes, {reupload: model.id}),
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

            var scope = data.scope || model.name || this.scope;

            var viewName = this.getMetadata().get('clientDefs.' + scope + '.modalViews.edit') || 'views/modals/edit';

            if (!this.quickEditDisabled) {
                Espo.Ui.notify(this.translate('loading', 'messages'));
                var options = {
                    scope: scope,
                    id: id,
                    model: model,
                    fullFormDisabled: this.getMetadata().get('clientDefs.' + scope + '.modalFullFormDisabled') || false,
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
                this.getRouter().navigate('#' + scope + '/edit/' + id, {trigger: false});
                this.getRouter().dispatch(scope, 'edit', options);
            }
        },

        actionDynamicAction: function (data) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow', {
                actionId: data.action_id,
                entityId: data.entity_id
            }).success(response => {
                if (response.inBackground) {
                    this.notify(this.translate('jobAdded', 'messages'), 'success');
                } else {
                    if (response.success) {
                        this.notify(response.message, 'success');
                        if (response.redirect) {
                            this.getRouter().navigate('#' + response.scope + '/view/' + response.entityId, {trigger: false});
                            this.getRouter().dispatch(response.scope, 'view', {
                                id: response.entityId,
                            })
                            return;
                        }
                    } else {
                        this.notify(response.message, 'error');
                    }
                }
                this.collection.fetch();
            });
        },

        getRowSelector: function (id) {
            return 'tr[data-id="' + id + '"]';
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

            this.confirm({
                message: (this.translate(parts.pop(), parts.pop(), parts.pop())).replace('{{name}}', model.get('name')),
                confirmText: this.translate('Remove')
            }, function () {
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
                    url: this.entityType + '/action/massRestore',
                    type: 'POST',
                    data: JSON.stringify({
                        ids:[id]
                    })
                }).done(function (result) {
                        this.notify('Restored', 'success');
                        this.removeRecordFromList(id);
                    }.bind(this)
                ).fail(function(){
                    this.notify('Error occured', 'error');
                    this.collection.push(model);
                }.bind(this))
            }, this);
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
        }
    });
});
