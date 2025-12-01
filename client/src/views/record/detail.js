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

Espo.define('views/record/detail', ['views/record/base', 'view-record-helper'], function (Dep, ViewRecordHelper) {

    return Dep.extend({

        template: 'record/detail',

        type: 'detail',

        isSmall: false,

        name: 'detail',

        layoutName: 'detail',

        fieldsMode: 'detail',

        gridLayout: null,

        detailLayout: null,

        buttonsDisabled: false,

        columnCount: 2,

        scope: null,

        isNew: false,

        additionalButtons: [],

        additionalEditButtons: [],

        route: [],

        realtimeId: null,

        buttonList: [
            {
                name: 'edit',
                label: 'Edit',
                style: 'primary',
            }
        ],

        dropdownItemList: [
            {
                name: 'delete',
                label: 'Remove'
            }
        ],

        buttonEditList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
                edit: true
            },
            {
                name: 'saveAndNext',
                label: 'Save and edit next',
                edit: true
            },
            {
                name: 'saveAndCreate',
                label: 'Save and Create',
                edit: true
            },
            {
                name: 'cancelEdit',
                label: 'Cancel',
                edit: true
            }
        ],

        dropdownEditItemList: [],

        id: null,

        returnUrl: null,

        returnDispatchParams: null,

        middleView: 'views/record/detail-middle',

        // legacy property
        sideView: true,

        bottomView: 'views/record/detail-bottom',

        sideDisabled: false,

        bottomDisabled: false,

        editModeDisabled: false,

        readOnly: false,

        isWide: false,

        dependencyDefs: {},

        duplicateAction: true,

        selfAssignAction: false,

        inlineEditDisabled: false,

        fetchOnModelAfterSaveError: true,

        panelNavigationView: 'views/record/panel-navigation',

        layoutData: null,

        mode: 'detail',

        listInlineEditModeEnabled: true,

        events: {
            'click .button-container .action': function (e) {
                var $target = $(e.currentTarget);
                var action = $target.data('action');
                var data = $target.data();
                if (action) {
                    var method = 'action' + Espo.Utils.upperCaseFirst(action);
                    if (typeof this[method] == 'function') {
                        this[method].call(this, data, e);
                        e.preventDefault();
                    }
                }
            },
            'click a[data-action="setAsInherited"]': function (e) {
                const $el = $(e.currentTarget);
                const field = $el.parents('.cell').data('name');
                this.ajaxPostRequest(`${this.scope}/action/inheritField`, {
                    field: field,
                    id: this.model.get('id')
                }).then(response => {
                    this.model.fetch().then(() => {
                        this.afterSave();
                        this.trigger('after:save');
                        this.model.trigger('after:save');
                        this.notify('Saved', 'success');
                    });
                });
            }
        },

        executeAction: function (action, data = null, e = null) {
            var method = 'action' + Espo.Utils.upperCaseFirst(action);
            if (typeof this[method] == 'function') {
                this[method].call(this, data, e);
            }
        },

        refreshLayout(middleOnly = false, callback) {
            this.detailLayout = null
            this.gridLayout = null

            const useNotification = !this.model._disableRefreshNotification
            if (useNotification) {
                this.notify('Loading...')
            }
            this.getGridLayout((layout) => {
                if (useNotification) {
                    this.notify(false)
                } else {
                    delete this.model._disableRefreshNotification
                }

                const middle = this.getView('middle')
                let reRenderMiddle = (middle) => {
                    middle._layout = layout
                    for (let key in middle.nestedViews) {
                        middle.clearView(key);
                    }
                    middle._loadNestedViews(() => {
                        if (middleOnly) {
                            middle.reRender()
                        } else {
                            this.reRender();
                        }

                        if (callback) {
                            callback()
                        }
                    })
                }
                if (middle) {
                    reRenderMiddle(middle)
                } else {
                    this.listenToOnce(this, 'after:render', () => {
                        reRenderMiddle(this.getView('middle'))
                    })
                }
            })
        },

        actionAddAttribute(panelName) {
            let boolFilterList = ['onlyForEntity', 'notForbiddenForEditFields'];
            let boolFilterData = {
                onlyForEntity: this.model.name,
                notForbiddenForEditFields: {
                    entityName: this.model.name,
                    entityId: this.model.get('id')
                }
            };

            if (typeof panelName === 'string') {
                boolFilterList.push('onlyForAttributePanel');
                boolFilterData['onlyForAttributePanel'] = panelName;
            }

            this.notify('Loading...');
            this.createView('dialog', 'views/modals/select-records', {
                scope: 'Attribute',
                multiple: true,
                createButton: false,
                massRelateEnabled: false,
                boolFilterList: boolFilterList,
                boolFilterData: boolFilterData
            }, dialog => {
                dialog.render();
                this.notify(false);
                dialog.once('select', selectObj => {
                    this.notify('Loading...');
                    const data = {
                        entityName: this.model.name,
                        entityId: this.model.get('id'),
                    }
                    if (Array.isArray(selectObj)) {
                        data.ids = selectObj.map(o => o.id)
                    } else {
                        data.where = selectObj.where
                    }
                    $.ajax({
                        url: `Attribute/action/addAttributeValue`,
                        type: 'POST',
                        data: JSON.stringify(data),
                        contentType: 'application/json',
                        success: () => {
                            this.model.fetch().then(() => {
                                this.notify('Saved', 'success');
                            });
                        }
                    });
                });
            });
        },

        showReloadPageMessage() {
            Espo.Ui.notify(this.translate('pleaseReloadPage'), 'info', 1000 * 10, true);
        },

        actionEdit: function () {
            if (!this.editModeDisabled) {
                this.setEditMode();
            } else {
                var options = {
                    id: this.model.id,
                    model: this.model
                };
                if (this.options.rootUrl) {
                    options.rootUrl = this.options.rootUrl;
                }
                this.getRouter().navigate('#' + this.scope + '/edit/' + this.model.id, { trigger: false });
                this.getRouter().dispatch(this.scope, 'edit', options);
            }
        },

        actionInheritAllForChildren: function () {
            this.confirm({
                message: this.translate('confirmInheritAllForChildren', 'messages'),
                confirmText: this.translate('Apply')
            }, () => {
                this.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest(this.scope + '/action/InheritAllForChildren', { id: this.model.id }).then(() => {
                    this.notify('Done', 'success');
                });
            });
        },

        actionInheritAllFromParent: function () {
            this.confirm({
                message: this.translate('confirmInheritAllFromParent', 'messages'),
                confirmText: this.translate('Apply')
            }, () => {
                this.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest(this.scope + '/action/InheritAllFromParent', { id: this.model.id }).then(() => {
                    this.notify('Done', 'success');
                });
            });
        },

        actionDynamicAction: function (data) {
            let defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).find(defs => defs.id === data.id)
            if (!defs) {
                defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicFieldActions']) || []).find(defs => defs.id === data.id)
            }

            if (defs && defs.type) {
                const method = 'actionDynamicAction' + Espo.Utils.upperCaseFirst(defs.type);
                if (typeof this[method] == 'function') {
                    this[method].call(this, data);
                    return
                }
            }

            this.executeActionRequest({
                actionId: data.id,
                entityId: this.model.get('id')
            })
        },

        actionDynamicActionSuggestValue(data) {
            if (this.mode !== 'edit') {
                return;
            }
            Espo.ui.notify(this.translate('loading', 'messages'))
            this.ajaxPostRequest('Action/action/ExecuteRecordAction', {
                actionId: data.id,
                entityId: this.model.get('id'),
                actionType: "suggestingValue",
                payload: {
                    uiRecord: this.model.attributes,
                    uiRecordFromName: this.model.attributes?._entityFrom?._entityName || null,
                    uiRecordFrom: this.model.attributes?._entityFrom || null,
                }
            }).success(res => {
                Espo.Ui.notify(false);
                if (res.toUpdate) {
                    this.model.set(res.data);
                }
            })
        },

        actionDynamicActionEmail(data) {
            const defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).find(defs => defs.id === data.id)
            if (!defs) {
                return
            }
            const payload = {
                actionId: data.id,
                entityId: this.model.get('id')
            }

            if (defs.showEmailPreview) {
                // show preview modal
                Espo.ui.notify(this.translate('loading', 'messages'))

                this.ajaxPostRequest('Action/action/ExecuteRecordAction', {
                    actionId: data.id,
                    entityId: this.model.get('id'),
                    actionType: "emailPreview"
                }).success(response => {
                    this.getModelFactory().create('EmailTemplate', model => {
                        model.set(response);
                        this.createView('dialog', 'views/modals/send-email', {
                            el: '[data-view="dialog"]',
                            model: model,
                            callback: (emailData) => {
                                this.executeActionRequest(Object.assign({}, payload, emailData), () => {
                                    if (this.getView('dialog')) {
                                        this.getView('dialog').dialog.close()
                                    }
                                })
                            }
                        }, view => {
                            view.render()
                            Espo.Ui.notify(false);
                        });
                    })
                })
            } else {
                this.executeActionRequest(payload)
            }
        },

        executeActionRequest(payload, callback) {
            this.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('Action/action/executeNow?silent=true', payload).success(response => {
                if (response.inBackground) {
                    this.notify(this.translate('jobAdded', 'messages'), 'success');
                } else {
                    if (response.success) {
                        this.notify(response.message, 'success');
                        if (response.redirect) {
                            const action = response.action ?? 'view'
                            this.getRouter().navigate('#' + response.scope + '/' + action + '/' + response.entityId, { trigger: false });
                            this.getRouter().dispatch(response.scope, action, {
                                id: response.entityId,
                            })
                            return;
                        }

                        window.dispatchEvent(new Event('record:actions-reload'));

                        if (callback) {
                            callback()
                        }
                    } else {
                        Espo.Ui.notify(response.message, 'error', null, true);
                    }
                }
                this.model.fetch();
            })
                .error(error => {
                    let message = error.responseText
                    if (!message && error.status === 403) {
                        message = this.translate('Action Forbidden', 'labels')
                    }
                    Espo.Ui.notify(message, 'error', null, true)
                })
        },

        actionDelete: function () {
            this.delete();
        },

        actionSaveAndNext: function () {
            this.save(function () {
                this.getParentView().actionNext();
            }.bind(this), true);
        },

        actionSaveAndCreate: function () {
            this.save(function () {
                this.getRouter().navigate('#' + this.scope + '/create', { trigger: false });
                this.getRouter().dispatch(this.scope, 'create');
            }.bind(this), true);
        },

        actionCancelEdit: function () {
            this.cancelEdit();
        },

        actionSelfAssign: function () {
            var attributes = {
                assignedUserId: this.getUser().id,
                assignedUserName: this.getUser().get('name')
            };
            if ('getSelfAssignAttributes' in this) {
                var attributesAdditional = this.getSelfAssignAttributes();
                if (attributesAdditional) {
                    for (var i in attributesAdditional) {
                        attributes[i] = attributesAdditional[i];
                    }
                }
            }
            this.model.save(attributes, {
                patch: true
            }).then(function () {
                Espo.Ui.success(this.translate('Self-Assigned'));
            }.bind(this));
        },

        actionCompareInstance: function () {
            if (!this.getAcl().check(this.entityType, 'read')) {
                this.notify('Access denied', 'error');
                return false;
            }
            let view = this.getMetadata().get(['clientDefs', this.entityType, 'modalViews', 'compare']) || 'views/modals/compare'
            this.createView('recordCompareInstance', view, {
                model: this.model,
                scope: this.scope,
                instanceComparison: true,
                mode: "details",
            }, function (dialog) {
                dialog.render();
                this.notify(false)
            });
        },

        getSelfAssignAttributes: function () {
        },

        setupActionItems: function () {
            if (this.getMetadata().get(['scopes', this.model.name, 'disabled']) || this.getMetadata().get(['scopes', this.model.name, 'type']) === 'Archive') {
                this.buttonList = []
                this.dropdownItemList = []
                this.buttonEditList = []
                this.dropdownEditItemList = []
                return
            }

            if (this.model.isNew()) {
                this.isNew = true;
                this.removeButton('delete');
            } else {
                if (!this.getAcl().check(this.model, 'delete')) {
                    this.removeButton('delete');
                }
            }

            if (this.duplicateAction) {
                if (this.getAcl().check(this.entityType, 'create')) {
                    this.dropdownItemList.push({
                        'label': 'Duplicate',
                        'name': 'duplicate'
                    });
                }
            }

            if (this.getAcl().check(this.entityType, 'edit') && this.getMetadata().get(`scopes.${this.model.name}.hasAttribute`) && !this.getMetadata().get(`scopes.${this.model.name}.disableAttributeLinking`) && this.getAcl().check(this.entityType, 'createAttributeValue')) {
                this.dropdownItemList.push({
                    label: 'addAttribute',
                    name: 'addAttribute'
                });
            }

            if (this.getMetadata().get(['clientDefs', this.entityType, 'showCompareAction'])) {
                if (this.getAcl().check(this.entityType, 'read') && this.mode !== 'edit') {
                    let exists = false;

                    for (const item of (this.dropdownItemList || [])) {
                        if (item.name === 'compare') {
                            exists = true;
                        }
                    }

                    let instances = this.getMetadata().get(['app', 'comparableInstances']);

                    if (!exists && instances.length) {
                        this.dropdownItemList.push({
                            'label': this.translate('Compare with') + ' ' + instances[0].name,
                            'name': 'compareInstance',
                            'action': 'compareInstance'
                        });
                    }
                }
            }

            if (this.isHierarchical() && !this.model.isNew()) {
                if (this.getAcl().check(this.entityType, 'edit') && this.model.get('hasChildren')) {
                    this.dropdownItemList.push({
                        'label': 'inheritAllForChildren',
                        'name': 'inheritAllForChildren'
                    });
                }

                if (this.getMetadata().get(`scopes.${this.scope}.multiParents`) !== true && this.model.get('parentId')) {
                    this.dropdownItemList.push({
                        'label': 'inheritAllFromParent',
                        'name': 'inheritAllFromParent'
                    });
                }
            }

            let dropdownItemList = [];
            (this.dropdownItemList || []).forEach(item => {
                if (!item.name || item.name !== 'dynamicAction') {
                    dropdownItemList.push(item);
                }
            });
            this.dropdownItemList = dropdownItemList;

            let additionalButtons = [];
            (this.additionalButtons || []).forEach(item => {
                if (!item.action || item.action !== 'dynamicAction') {
                    additionalButtons.push(item);
                }
            });
            this.additionalButtons = additionalButtons;

            if (this.selfAssignAction) {
                if (
                    this.getAcl().check(this.entityType, 'edit')
                    &&
                    !~this.getAcl().getScopeForbiddenFieldList(this.entityType).indexOf('assignedUser')
                ) {
                    if (this.model.has('assignedUserId')) {
                        this.dropdownItemList.push({
                            'label': 'Self-Assign',
                            'name': 'selfAssign',
                            'hidden': !!this.model.get('assignedUserId')
                        });
                        this.listenTo(this.model, 'change:assignedUserId', function () {
                            if (!this.model.get('assignedUserId')) {
                                this.showActionItem('selfAssign');
                            } else {
                                this.hideActionItem('selfAssign');
                            }
                        }, this);
                    }
                }
            }

            if (this.type === 'detail' && this.getMetadata().get(['scopes', this.scope, 'hasPersonalData'])) {
                if (this.getAcl().get('dataPrivacyPermission') !== 'no') {
                    this.dropdownItemList.push({
                        'label': 'View Personal Data',
                        'name': 'viewPersonalData'
                    });
                }
            }

            const dropDownItems = this.getMetadata().get(['clientDefs', this.scope, 'additionalDropdownItems']) || {};
            Object.keys(dropDownItems).forEach(item => {
                const check = (dropDownItems[item].conditions || []).every(condition => {
                    let check;
                    switch (condition.type) {
                        case 'type':
                            check = this.type === condition.value;
                            break;
                        default:
                            check = true;
                            break;
                    }
                    return check;
                });

                if (check) {
                    let dropdownItem = {
                        name: dropDownItems[item].name,
                        label: dropDownItems[item].label
                    };
                    if (dropDownItems[item].iconClass) {
                        let htmlLogo = `<span class="additional-action-icon ${dropDownItems[item].iconClass}"></span>`;
                        dropdownItem.html = `${this.translate(dropDownItems[item].label, 'labels', this.scope)} ${htmlLogo}`;
                    }
                    this.dropdownItemList.push(dropdownItem);

                    let method = 'action' + Espo.Utils.upperCaseFirst(dropDownItems[item].name);
                    this[method] = function () {
                        let path = dropDownItems[item].actionViewPath;

                        let o = { dropdownItem: dropDownItems[item] };
                        (dropDownItems[item].optionsToPass || []).forEach((option) => {
                            if (option in this) {
                                o[option] = this[option];
                            }
                        });

                        this.createView(item, path, o, (view) => {
                            if (typeof view[dropDownItems[item].action] === 'function') {
                                view[dropDownItems[item].action]();
                            }
                        });
                    };
                }
            }, this);

            additionalButtons = this.getMetadata().get(['clientDefs', this.scope, 'additionalButtons']) || {};

            Object.keys(additionalButtons).forEach(item => {
                const check = (additionalButtons[item].conditions || []).every(condition => {
                    let check;
                    switch (condition.type) {
                        case 'type':
                            check = this.type === condition.value;
                            break;
                        default:
                            check = true;
                            break;
                    }
                    return check;
                });

                if (check) {
                    let button = {
                        name: additionalButtons[item].name,
                        label: additionalButtons[item].label,
                        action: additionalButtons[item].name
                    };

                    this.additionalButtons.push(button);

                    let method = 'action' + Espo.Utils.upperCaseFirst(additionalButtons[item].name);
                    this[method] = function () {
                        let path = additionalButtons[item].actionViewPath;

                        let o = { button: additionalButtons[item] };

                        (additionalButtons[item].optionsToPass || []).forEach((option) => {
                            if (option in this) {
                                o[option] = this[option];
                            }
                        });

                        this.createView(item, path, o, (view) => {
                            if (typeof view[additionalButtons[item].action] === 'function') {
                                view[additionalButtons[item].action]();
                            }
                        });
                    };
                }
            }, this);

            if (this.getMetadata().get(['scopes', this.scope, 'enabledCopyConfigurations']) && this.getAcl().check(this.entityType, 'read')) {
                this.dropdownItemList.push({
                    'label': this.translate('copyConfigurations', 'labels', 'Global'),
                    'name': 'copyConfigurations'
                });
            }
        },

        isHierarchical() {
            return this.getMetadata().get(`scopes.${this.scope}.type`) === 'Hierarchy';
        },

        disableActionItems: function () {
            this.disableButtons();
        },

        enableActionItems: function () {
            this.enableButtons();
        },

        hideActionItem: function (name) {
            for (var i in this.buttonList) {
                if (this.buttonList[i].name == name) {
                    this.buttonList[i].hidden = true;
                    break;
                }
            }
            for (var i in this.dropdownItemList) {
                if (this.dropdownItemList[i].name == name) {
                    this.dropdownItemList[i].hidden = true;
                    break;
                }
            }

            if (this.isRendered()) {
                window.dispatchEvent(new CustomEvent('record:buttons-update', { detail: this.getRecordButtons() }));
            }
        },

        showActionItem: function (name) {
            for (var i in this.buttonList) {
                if (this.buttonList[i].name == name) {
                    this.buttonList[i].hidden = false;
                    break;
                }
            }
            for (var i in this.dropdownItemList) {
                if (this.dropdownItemList[i].name == name) {
                    this.dropdownItemList[i].hidden = false;
                    break;
                }
            }

            if (this.isRendered()) {
                window.dispatchEvent(new CustomEvent('record:buttons-update', { detail: this.getRecordButtons() }));
            }
        },

        showPanel: function (name) {
            this.recordHelper.setPanelStateParam(name, 'hidden', false);

            var middleView = this.getView('middle');
            if (middleView) {
                middleView.showPanel(name);
            }

            var bottomView = this.getView('bottom');
            if (bottomView) {
                if ('showPanel' in bottomView) {
                    bottomView.showPanel(name);
                }
            }
        },

        hidePanel: function (name) {
            this.recordHelper.setPanelStateParam(name, 'hidden', true);

            var middleView = this.getView('middle');
            if (middleView) {
                middleView.hidePanel(name);
            }

            var bottomView = this.getView('bottom');
            if (bottomView) {
                if ('hidePanel' in bottomView) {
                    bottomView.hidePanel(name);
                }
            }
        },

        initListenToInlineMode: function () {
            var fields = this.getFieldViews();

            var fieldInEditMode = null;
            for (var field in fields) {
                var fieldView = fields[field];
                this.listenTo(fieldView, 'edit', function (view) {
                    if (fieldInEditMode && fieldInEditMode.mode == 'edit') {
                        // fieldInEditMode.inlineEditClose(); // if the value can't be saved the field shouldn't be closed.
                    }
                    fieldInEditMode = view;
                }, this);

                this.listenTo(fieldView, 'inline-edit-on', function () {
                    this.inlineEditModeIsOn = true;
                }, this);
                this.listenTo(fieldView, 'inline-edit-off', function () {
                    this.inlineEditModeIsOn = false;
                    this.setIsNotChanged();
                }, this);
            }
        },

        afterRender: function () {
            if (!this.options.disableRealtimeListener) {
                this.initRealtimeListener();
            }

            window.dispatchEvent(new CustomEvent('record:buttons-update', { detail: this.getRecordButtons() }));

            var $container = this.$el.find('.detail-button-container');

            var stickTop = this.getThemeManager().getParam('stickTop') || 62;
            var blockHeight = this.getThemeManager().getParam('blockHeight') || ($container.innerHeight() / 2);

            var $window = $(window);

            var screenWidthXs = this.getThemeManager().getParam('screenWidthXs');

            this.initListenToInlineMode();

            let searchContainer = $('.page-header .search-container');
            if (searchContainer.length && !this.$el.parents('.modal-container').length) {
                searchContainer.addClass('hidden');
            }

            $window.off('scroll.detail-' + this.numId);
            $window.on('scroll.detail-' + this.numId, function (e) {
                if ($(window.document).width() < screenWidthXs) {
                    $container.show();
                    return;
                }

                const position = this.$el.position();

                if (position && 'top' in position) {
                    var edge = position.top + this.$el.outerHeight(true);
                    var scrollTop = $window.scrollTop();

                    if (scrollTop < edge) {
                        if (scrollTop > stickTop) {
                            if (!$container.hasClass('stick-sub') && this.mode !== 'edit') {
                                var $p = $('.popover:not(.note-popover)');
                                $p.each(function (i, el) {
                                    var $el = $(el);
                                    $el.css('top', ($el.position().top - ($container.height() - blockHeight * 2 + 10)) + 'px');
                                }.bind(this));
                            }
                        } else {
                            if ($container.hasClass('stick-sub') && this.mode !== 'edit') {
                                var $p = $('.popover:not(.note-popover)');
                                $p.each(function (i, el) {
                                    var $el = $(el);
                                    $el.css('top', ($el.position().top + ($container.height() - blockHeight * 2 + 10)) + 'px');
                                }.bind(this));
                            }
                        }
                        var $p = $('.popover');
                        $p.each(function (i, el) {
                            var $el = $(el);
                            let top = $el.css('top').slice(0, -2);
                            if (top > 0 && scrollTop > 0 && top > scrollTop) {
                                if (stickTop > $container.height()) {
                                    if (top - scrollTop > stickTop) {
                                        $el.removeClass('hidden');
                                    } else {
                                        $el.addClass('hidden');
                                    }
                                } else {
                                    if (top - scrollTop > ($container.height() + blockHeight * 2 + 10)) {
                                        $el.removeClass('hidden');
                                    } else {
                                        $el.addClass('hidden');
                                    }
                                }
                            }
                        }.bind(this));
                    }
                }
            }.bind(this));
        },

        fetch: function (onlyRelation) {
            var data = Dep.prototype.fetch.call(this, onlyRelation);
            if (onlyRelation) {
                return data
            }

            if (this.hasView('bottom')) {
                var view = this.getView('bottom');
                if ('fetch' in view) {
                    data = _.extend(data, view.fetch());
                }
            }
            return data;
        },

        setEditMode: function () {
            this.trigger('before:set-edit-mode');
            this.disableButtons();

            var fields = this.getFieldViews(true);
            var count = Object.keys(fields || {}).length;
            for (var field in fields) {
                var fieldView = fields[field];
                if (!fieldView.readOnly) {
                    if (fieldView.mode === 'edit') {
                        fieldView.fetchToModel();
                        fieldView.removeInlineEditLinks();
                        fieldView.inlineEditModeIsOn = false;
                        fieldView.killAfterOutsideClickListener()
                    }
                    fieldView.setMode('edit');
                    fieldView.render(() => {
                        count--;
                        if (count === 0) {
                            this.enableButtons();
                        }
                    });
                } else {
                    count--;
                    if (count === 0) {
                        this.enableButtons();
                    }
                }
            }
            this.mode = 'edit';
            this.trigger('after:set-edit-mode');
            this.triggerModeChangedOnModel('edit');
        },

        setDetailMode: function () {
            this.trigger('before:set-detail-mode');

            var fields = this.getFieldViews(true);
            for (var field in fields) {
                var fieldView = fields[field];
                if (fieldView.mode != 'detail') {
                    if (fieldView.mode === 'edit') {
                        fieldView.trigger('inline-edit-off');
                    }
                    fieldView.setMode('detail');
                    fieldView.render();
                }
            }
            this.mode = 'detail';
            this.trigger('after:set-detail-mode');
            this.triggerModeChangedOnModel('detail')
        },

        triggerModeChangedOnModel(mode) {
            this.model.trigger('after:change-mode', mode);
        },

        cancelEdit: function () {
            this.resetModelChanges();

            this.setDetailMode();
            this.setIsNotChanged();
        },

        resetModelChanges: function () {
            var attributes = this.model.attributes;
            for (var attr in attributes) {
                if (!(attr in this.attributes)) {
                    this.model.unset(attr);
                }
            }

            this.model.set(this.attributes);
        },

        delete: function () {
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
                this.trigger('before:delete');
                this.trigger('delete');

                this.notify('removing');

                var collection = this.model.collection;

                this.model.destroy({
                    wait: true,
                    error: function () {
                        this.notify('Error occured!', 'error');
                    }.bind(this),
                    success: function () {
                        if (collection) {
                            if (collection.total > 0) {
                                collection.total--;
                            }
                        }

                        this.notify('Removed', 'success');
                        this.trigger('after:delete');
                        this.exit('delete');
                    }.bind(this),
                });
            }

            if (this.getMetadata().get(['scopes', this.scope, 'deleteWithoutConfirmation'])) {
                action();
                return;
            }

            this.confirm({
                message: (this.translate(parts.pop(), parts.pop(), parts.pop())).replace('{{name}}', this.model.get('name')),
                confirmText: this.translate('Remove')
            }, () => action(), this);
        },

        getFieldViews: function (withHidden) {
            var fields = {};

            if (this.hasView('middle')) {
                if ('getFieldViews' in this.getView('middle')) {
                    _.extend(fields, Espo.Utils.clone(this.getView('middle').getFieldViews(withHidden)));
                }
            }

            if (this.hasView('bottom')) {
                if ('getFieldViews' in this.getView('bottom')) {
                    _.extend(fields, this.getView('bottom').getFieldViews(withHidden));
                }
            }
            return fields;
        },

        getFieldView: function (name) {
            var view;
            if (this.hasView('middle')) {
                view = this.getView('middle').getFieldView(name);
            }

            if (!view && this.hasView('bottom')) {
                view = (this.getView('bottom').getFieldViews(true) || {})[name];
            }
            return view || null;
        },

        // TODO remove
        handleDataBeforeRender: function (data) {
        },

        data: function () {

            if (!this.options.hasNext) {
                this.buttonEditList = (this.buttonEditList || []).filter(row => {
                    return row.name !== 'saveAndNext';
                });
                this.buttonList = (this.buttonList || []).filter(row => {
                    return row.name !== 'saveAndNext';
                });
            }

            let data = {
                additionalButtons: this.additionalButtons,
                additionalEditButtons: this.additionalEditButtons,
                scope: this.scope,
                entityType: this.entityType,
                buttonList: this.buttonList,
                buttonEditList: this.buttonEditList,
                dropdownItemList: this.isDropdownItemListEmpty() ? [] : this.dropdownItemList,
                dropdownEditItemList: this.dropdownEditItemList,
                dropdownItemListEmpty: this.isDropdownItemListEmpty(),
                buttonsDisabled: this.buttonsDisabled,
                hasButtons: this.buttonList.length > 0 || this.dropdownItemList.length > 0 || this.additionalButtons.length > 0,
                name: this.name,
                id: this.id,
                isWide: this.isWide,
                isSmall: this.isSmall
            };

            return data;
        },

        getAdditionalButtons: function () {
            return [];
        },

        init: function () {
            this.entityType = this.model.name;
            this.scope = this.options.scope || this.entityType;

            this.layoutName = this.options.layoutName || this.layoutName;

            this.detailLayout = this.options.detailLayout || this.detailLayout;

            this.type = this.options.type || this.type;

            this.buttons = this.options.buttons || this.buttons;
            this.buttonList = this.options.buttonList || this.buttonList;
            this.dropdownItemList = this.options.dropdownItemList || this.dropdownItemList;

            this.buttonList = _.clone(this.buttonList);
            this.buttonEditList = _.clone(this.buttonEditList);
            this.dropdownItemList = _.clone(this.dropdownItemList);
            this.dropdownEditItemList = _.clone(this.dropdownEditItemList);

            this.returnUrl = this.options.returnUrl || this.returnUrl;
            this.returnDispatchParams = this.options.returnDispatchParams || this.returnDispatchParams;

            this.exit = this.options.exit || this.exit;
            this.columnCount = this.options.columnCount || this.columnCount;

            Bull.View.prototype.init.call(this);
        },

        isDropdownItemListEmpty: function () {
            if (!this.model.id) {
                return true;
            }

            if (this.dropdownItemList.length === 0) {
                return true;
            }

            var isEmpty = true;
            this.dropdownItemList.forEach(function (item) {
                if (!item.hidden) {
                    isEmpty = false;
                }
            }, this);

            return isEmpty;
        },

        setup: function () {
            if (typeof this.model === 'undefined') {
                throw new Error('Model has not been injected into record view.');
            }

            this.recordHelper = new ViewRecordHelper(this.defaultFieldStates, this.defaultFieldStates);

            this.once('remove', function () {
                if (this.isChanged) {
                    this.resetModelChanges();
                }
                this.setIsNotChanged();
                $(window).off('scroll.detail-' + this.numId);
            }, this);

            this.numId = Math.floor((Math.random() * 10000) + 1);
            this.id = Espo.Utils.toDom(this.entityType) + '-' + Espo.Utils.toDom(this.type) + '-' + this.numId;

            if (_.isUndefined(this.events)) {
                this.events = {};
            }

            if (!this.editModeDisabled) {
                if ('editModeDisabled' in this.options) {
                    this.editModeDisabled = this.options.editModeDisabled;
                } else if (this.getMetadata().get(['scopes', this.model.name, 'disabled'])) {
                    this.editModeDisabled = true
                }
            }

            this.buttonsDisabled = this.options.buttonsDisabled || this.buttonsDisabled;

            // for backward compatibility
            // TODO remove in 5.6.0
            if ('buttonsPosition' in this.options && !this.options.buttonsPosition) {
                this.buttonsDisabled = true;
            }

            if ('isWide' in this.options) {
                this.isWide = this.options.isWide;
            }

            if ('bottomView' in this.options) {
                this.bottomView = this.options.bottomView;
            }

            this.sideDisabled = this.options.sideDisabled || this.sideDisabled;
            this.bottomDisabled = this.options.bottomDisabled || this.bottomDisabled;

            this.readOnlyLocked = this.readOnly;
            this.readOnly = this.options.readOnly || this.readOnly;

            if (this.getMetadata().get(['clientDefs', this.entityType, 'createDisabled']) || this.getMetadata().get(['scopes', this.entityType, 'disabled'])) {
                this.duplicateAction = false;
            }

            this.inlineEditDisabled = this.inlineEditDisabled || this.getMetadata().get(['clientDefs', this.scope, 'inlineEditDisabled'])
                || this.getMetadata().get(['scopes', this.model.name, 'disabled']) || false;

            this.inlineEditDisabled = this.options.inlineEditDisabled || this.inlineEditDisabled;

            if (!this.getAcl().check(this.entityType, 'create') || !this.getAcl().check(this.entityType, 'edit')) {
                this.buttonEditList = (this.buttonEditList || []).filter(item => {
                    return item.name !== 'saveAndCreate'
                })
            }

            this.additionalButtons = [];
            this.dropdownItemList = [{
                name: 'delete',
                label: 'Remove'
            }];

            this.isNew = this.model.isNew()

            this.setupBeforeFinal();

            this.onModelReady(() => {
                this.setupActionItems();
                window.dispatchEvent(new CustomEvent('record:buttons-update', { detail: this.getRecordButtons() }));
            })

            if (this.collection) {
                this.stopListening(this.model, 'destroy');
                this.listenTo(this.model, 'destroy', function () {
                    this.collection.fetch();
                }, this);
            }

            $(window).off('keydown.actions').on('keydown.actions', e => {
                if (e.keyCode === 69 && e.ctrlKey && !$('body').hasClass('modal-open')) {
                    this.hotKeyEdit(e);
                }
                if (e.keyCode === 83 && e.ctrlKey && !$('body').hasClass('modal-open')) {
                    this.hotKeySave(e);
                }

                if (e.key === "Escape") {
                    $('.inline-cancel-link').click();
                    $('button[data-name="cancelEdit"]').click();
                }
            });

            if (!this.model.isNew() && (this.type === 'detail' || this.type === 'edit') && !this.isSmall) {
                this.listenTo(this, 'after:render', () => {
                    this.applyOverviewFilters();
                });
                this.listenTo(this.model, 'sync overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });
            }

            if (this.layoutName === 'detail') {
                this.listenTo(this.model, 'toggle-required-fields-highlight', () => {
                    this.highlightRequired();
                });

                this.listenTo(this.model, 'sync', () => {
                    if (this.layoutHasActionFields()) {
                        this.fetchDynamicFieldActions(() => {
                            if (this.mode !== 'edit') {
                                this.refreshLayout(true);
                            }
                        })
                    } else {
                        if (this.mode !== 'edit') {
                            this.refreshLayout();
                        }
                    }
                });

                this.addToLanguageObservables();

                this.listenTo(this, 'change:disabled-languages', (value) => {
                    this.getUser().set('disabledLanguages', value)
                    this.refreshLayout(true, () => {
                        if (this.mode === 'edit' && this.getView('middle')) {
                            this.setEditMode()
                        }
                    });
                })
            }

            this.listenTo(this.model, 'sync', () => {
                this.putAttributesToModel();
            });

            if (this.getMetadata().get(`scopes.${this.model.name}.hasClassification`)) {
                this.listenTo(this.model, 'after:save', () => {
                    if (this.model.changed.classificationsIds) {
                        this.model.fetch();
                    }
                });
            }

            this.listenTo(this.model, 'after:save', () => {
                window.dispatchEvent(new Event('record:actions-reload'));
                window.dispatchEvent(new Event('record:save'));
            });

            const dynamicOnFieldFocusActions = this.getMetadata().get(`clientDefs.${this.model.name}.dynamicOnFieldFocusActions`) || [];

            this.listenTo(this.model, 'focusField', field => {
                dynamicOnFieldFocusActions.forEach(item => {
                    if (item.focusField === field) {
                        this.executeDynamicAction(item);
                    }
                })
            });

            const dynamicOnRecordLoadActions = this.getMetadata().get(`clientDefs.${this.model.name}.dynamicOnRecordLoadActions`) || [];
            this.listenTo(this, 'after:render after:change-mode', () => {
                if (this.mode === 'edit') {
                    dynamicOnRecordLoadActions.forEach(item => {
                        this.executeDynamicAction(item);
                    });
                }
            });
            this.listenTo(this.model, 'after:change-mode', (type) => {
                if (this.mode === 'edit') {
                    dynamicOnRecordLoadActions.forEach(item => {
                        this.executeDynamicAction(item);
                    });
                }
            });
        },

        executeDynamicAction(item) {
            if (item.type) {
                const method = 'actionDynamicAction' + Espo.Utils.upperCaseFirst(item.type);
                if (typeof this[method] == 'function') {
                    this[method].call(this, item);
                    return
                }
            }

            this.executeActionRequest({
                actionId: item.id,
                entityId: this.model.get('id')
            })
        },

        highlightRequired() {
            let highlight = $('.highlighted-required').length === 0;
            $(`.required-sign`).each((k, el) => {
                let $cell = $(el).parents('.cell');
                // for product attribute value panel
                if ($cell.attr('data-name') === 'attribute') {
                    $cell = $cell.parents('tr');
                }

                if (!highlight) {
                    $cell.removeClass('highlighted-required');
                } else {
                    $cell.addClass('highlighted-required');
                }
            });
        },

        checkRealtimeListener(res) {
            setTimeout(() => {
                let id = this.$el.find('.detail').attr('id');
                if (id && this.realtimeId === this.model.get('id')) {
                    if (this.mode !== 'edit') {
                        $.ajax(`${res.endpoint}?silent=true&time=${$.now()}`, { local: true }).done(data => {
                            if (data.timestamp !== res.timestamp && $('.inline-cancel-link').length === 0) {
                                res.timestamp = data.timestamp;
                                if (!this.model._updatedById || this.model._updatedById !== this.getUser().id) {
                                    this.model._disableRefreshNotification = true
                                    this.model.fetch();
                                }
                                this.model._updatedById = undefined;
                            }
                        });
                    }
                    this.checkRealtimeListener(res);
                }
            }, 3000);
        },

        initRealtimeListener() {
            if (!this.model.get('id') || this.realtimeId === this.model.get('id')) {
                return;
            }

            if (!this.realtimeId || this.realtimeId !== this.model.get('id')) {
                this.realtimeId = this.model.get('id');
            }

            this.ajaxPostRequest('App/action/startEntityListening', {
                entityName: this.model.name,
                entityId: this.model.get('id')
            }).success(res => {
                this.checkRealtimeListener(res);
            });
        },

        hotKeyEdit: function (e) {
            e.preventDefault();
            if (this.mode !== 'edit') {
                this.actionEdit();
            }
        },

        hotKeySave: function (e) {
            e.preventDefault();
            if (this.mode === 'edit') {
                this.actionSave();
            } else {
                let viewsFields = this.getFieldViews();
                Object.keys(viewsFields).forEach(item => {
                    if (viewsFields[item].mode === "edit") {
                        viewsFields[item].inlineEditSave();
                    }
                });
            }
        },

        setupBeforeFinal: function () {
            this.listenToOnce(this.model, 'sync', function () {
                this.manageAccess();
            });

            this.attributes = this.model.getClonedAttributes();

            if (this.options.attributes) {
                this.model.set(this.options.attributes);
            }

            this.listenTo(this.model, 'sync after:inlineEditSave', function (model, data) {
                this.attributes = this.model.getClonedAttributes();


                if (data) {
                    Object.keys(data).forEach(field => {
                        if (!Array.isArray(this.attributes[field]) && !(this.attributes[field] !== null && typeof this.attributes[field] === 'object')) {
                            if (data[field] && data[field] !== this.attributes[field]) {
                                this.attributes[field] = data[field];
                            }
                        }
                    });
                }
            }, this);

            this.listenTo(this.model, 'change', () => {
                if (this.mode === 'edit' || this.inlineEditModeIsOn) {
                    this.setIsChanged();
                }
                if (['edit', 'detail'].includes(this.mode)) {
                    setTimeout(() => {
                        this.togglePanelsVisibility();
                    }, 200);
                }
            });

            this.dependencyDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.formDependency') || {}, this.dependencyDefs);
            this.initDependancy();

            this.setupFieldLevelSecurity();

            this.listenTo(this, 'after:render', () => {
                this.togglePanelsVisibility();
            })

            this.initDynamicHandler();
        },

        togglePanelsVisibility() {
            this.$el.find('.middle > .panel').each((k, el) => {
                const $panel = $(el);

                const shown = $panel.find('> .panel-body > .row > .cell').length;
                const hidden = $panel.find('> .panel-body > .row > .cell').filter(function () {
                    return $(this).css('display') === 'none';
                }).length;

                if (shown !== hidden) {
                    $panel.show();
                } else {
                    $panel.hide();
                }
            });
        },

        initDynamicHandler: function () {
            var dynamicHandlerClassName = this.dynamicHandlerClassName || this.getMetadata().get(['clientDefs', this.model.name, 'dynamicHandler']);
            if (dynamicHandlerClassName) {
                this.addReadyCondition(function () {
                    return !!this.dynamicHandler;
                }.bind(this));

                require(dynamicHandlerClassName, function (DynamicHandler) {
                    this.dynamicHandler = new DynamicHandler(this);

                    this.listenTo(this.model, 'change', function (model, o) {
                        if ('onChange' in this.dynamicHandler) {
                            this.dynamicHandler.onChange.call(this.dynamicHandler, model, o);
                        }

                        var changedAttributes = model.changedAttributes();
                        for (var attribute in changedAttributes) {
                            var methodName = 'onChange' + Espo.Utils.upperCaseFirst(attribute);
                            if (methodName in this.dynamicHandler) {
                                this.dynamicHandler[methodName].call(this.dynamicHandler, model, changedAttributes[attribute], o);
                            }
                        }
                    }, this);

                    if ('init' in this.dynamicHandler) {
                        this.dynamicHandler.init();
                    }

                    this.tryReady();
                }.bind(this));
            }
        },

        applyOverviewFilters() {
            // skip overview filters
            if (!this.model || this.model.isNew()
                || !this.getMetadata().get(['scopes', this.scope, 'object'])
                || this.getMetadata().get(['scopes', this.scope, 'overviewFilters']) === false
                || this.getMetadata().get(['scopes', this.scope, 'hideFieldTypeFilters']) === true
            ) {
                return
            }

            const fieldFilter = this.getStorage().get('fieldFilter', this.scope) || ['allValues'];

            $.each(this.getFieldViews(), (name, fieldView) => {
                name = fieldView.name || name
                if (fieldView.model.getFieldParam(name, 'advancedFilterDisabled') === true) {
                    return;
                }

                let fields = this.getFieldManager().getActualAttributeList(fieldView.model.getFieldType(name), name);
                let fieldValues = fields.map(field => fieldView.model.get(field));

                let hide = false;

                if (!fieldFilter.includes('allValues')) {
                    // hide filled
                    if (!hide && fieldFilter.includes('filled')) {
                        hide = fieldValues.every(value => this.isEmptyValue(value));
                    }

                    // hide empty
                    if (!hide && fieldFilter.includes('empty')) {
                        hide = !fieldValues.every(value => this.isEmptyValue(value));
                    }

                    // hide optional
                    if (!hide && fieldFilter.includes('optional')) {
                        hide = fieldView.isRequired();
                    }

                    // hide required
                    if (!hide && fieldFilter.includes('required')) {
                        hide = !fieldView.isRequired();
                    }
                }

                this.controlFieldVisibility(fieldView, hide);
            });
        },

        isEmptyValue(value) {
            return value === null || value === '' || (Array.isArray(value) && !value.length);
        },

        controlFieldVisibility(field, hide) {
            if (hide && !field.$el.hasClass('hidden')) {
                field.hide();
                field.overviewFiltersHidden = true;
            } else if (!hide && field.overviewFiltersHidden) {
                field.show();
            }
        },

        setupFinal: function () {
            this.build(this.addCollapsingButtonsToMiddleView);
        },

        addCollapsingButtonsToMiddleView(view) {
            view.listenTo(view, 'after:render', view => {
                // add layout configuration button
                const additionalLayouts = this.getMetadata().get(['clientDefs', this.model.name, 'additionalLayouts']) || {};
                const availableLayouts = []
                for (let key in additionalLayouts) {
                    if (['detail'].includes(additionalLayouts[key])) {
                        availableLayouts.push(key)
                    }
                }
                if (this.getMetadata().get(['scopes', this.model.name, 'layouts']) &&
                    ['detail', ...availableLayouts].includes(this.layoutName) &&
                    this.getAcl().check('LayoutProfile', 'read')
                    && this.mode !== 'edit'
                ) {

                    let html = `<div class="layout-editor-container pull-right"></div>`
                    const $parent = view.$el.find('.panel-heading:first')
                    if ($parent.length > 0) {
                        $parent.append(html)
                    } else {
                        // if first panel has no header
                        view.$el.find('.panel:first').prepend(`<div class="panel-heading">${html}</div>`)
                    }

                    this.createView('layoutConfigurator', "views/record/layout-configurator", {
                        scope: this.model.name,
                        viewType: this.layoutName,
                        layoutData: this.layoutData,
                        relatedScope: this.options.layoutRelatedScope,
                        el: this.getSelector() + '.panel-heading .layout-editor-container',
                    }, (view) => {
                        view.on("refresh", () => this.refreshLayout(true))
                        view.render()
                    })
                }

                // Add buttons on attribute panels
                let attributePanels = ['attributeValues'];
                $.each((this.getConfig().get('referenceData')?.AttributePanel || {}), (code, panel) => {
                    attributePanels.push(panel.id);
                })

                this.$el.find('.panel-heading').each((k, el) => {
                    let $el = $(el);
                    let $panelBody = $el.parent().find('.panel-body')
                    let panelName = $el.parent().data('name');
                    let isAttributeValuePanel = attributePanels.includes(panelName);

                    if (isAttributeValuePanel && this.getMetadata().get(['scopes', this.model.name, 'hasAttribute']) && this.getAcl().check(this.model.name, 'createAttributeValue')) {

                        if (!this.isSmall) {
                            const linkingEnabled = !this.getMetadata().get(['scopes', this.model.name, 'disableAttributeLinking'])

                            let html = '<div class="btn-group pull-right">' +
                                (linkingEnabled ? '<button type="button" class="btn btn-default btn-sm add-attribute-value-container"><i class="ph ph-plus"></i></button>' : '') +
                                '<button type="button" class="btn btn-default btn-sm close-attribute-panel" ><i class="ph ph-x"></i></button>' +
                                '</div>';
                            $el.append(html);
                            if (linkingEnabled) {
                                $el.find('.add-attribute-value-container').click(() => {
                                    this.actionAddAttribute(panelName);
                                });
                            }
                            $el.find('.close-attribute-panel').click(() => {
                                this.confirm({
                                    message: this.translate('closePanelConfirmation', 'messages'),
                                    confirmText: this.translate('Close')
                                }, () => {
                                    this.actionCloseAttributeValuePanel(panelName);
                                });
                            });
                        }

                        $panelBody.on('show.bs.collapse', (event) => {
                            if ($panelBody.is(event.target)) {
                                let collapsedPanels = this.getStorage().get('collapsed-attribute-panels', this.scope) || [];
                                if (collapsedPanels.includes(panelName)) {
                                    collapsedPanels = collapsedPanels.filter(item => item !== panelName)
                                    this.getStorage().set('collapsed-attribute-panels', this.scope, collapsedPanels)
                                    $el.find('.collapser').html('<i class="ph ph-caret-down"></i>');
                                }
                            }
                        })

                        $panelBody.on('hide.bs.collapse', (event) => {
                            if ($panelBody.is(event.target)) {
                                let collapsedPanels = this.getStorage().get('collapsed-attribute-panels', this.scope) || [];
                                if (!collapsedPanels.includes(panelName)) {
                                    collapsedPanels.push(panelName)
                                    this.getStorage().set('collapsed-attribute-panels', this.scope, collapsedPanels)
                                    $el.find('.collapser').html('<i class="ph ph-caret-right"></i>')
                                }
                            }
                        })


                        // apply collapse state
                        let collapsedPanels = this.getStorage().get('collapsed-attribute-panels', this.scope) || [];
                        $panelBody.addClass(collapsedPanels.includes(panelName) ? 'collapse' : 'collapse in');

                        $el.find('.panel-title').prepend(`<span class="collapser"><i class="ph ph-caret-${collapsedPanels.includes(panelName) ? 'right' : 'down'}"></i></span>`)

                        $el.find('.collapser').click(() => {
                            $panelBody.collapse('toggle')
                        })

                        this.trigger('detailPanelsLoaded', { list: this.getMiddlePanels().concat(this.getView('bottom')?.panelList || []) });
                    }
                });
            });
        },

        actionCloseAttributeValuePanel: function (name) {
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {}
            let panels = scopePreferences['closedAttributePanels'] ?? []
            if (!panels.includes(name)) {
                panels.push(name)
            }
            scopePreferences['closedAttributePanels'] = panels;

            preferences[this.scope] = scopePreferences;
            this.getPreferences().set('closedPanelOptions', preferences);
            this.getPreferences().save({ patch: true });
            this.getPreferences().trigger('update');

            this.refreshLayout(true)
        },

        showAttributeValuePanel: function (name, callback) {
            let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? {}
            let panels = scopePreferences['closedAttributePanels'] ?? []
            scopePreferences['closedAttributePanels'] = panels.filter(item => item !== name);

            preferences[this.scope] = scopePreferences;
            this.getPreferences().set('closedPanelOptions', preferences);
            this.getPreferences().save({ patch: true });
            this.getPreferences().trigger('update');

            if (callback) {
                this.listenToOnce(this.getView('middle'), 'after:render', () => {
                    callback()
                })
            }

            this.refreshLayout(true)
        },

        setIsChanged: function () {
            this.isChanged = true;
            this.setConfirmLeaveOut(true);
        },

        setIsNotChanged: function () {
            this.isChanged = false;
            this.setConfirmLeaveOut(false);
        },

        actionSave: function () {
            let savingCanceled = false;

            this.listenToOnce(this, 'cancel:save', () => savingCanceled = true);

            const setDetailAndScroll = () => {
                this.setDetailMode();
            };

            if (this.save(setDetailAndScroll, true) && savingCanceled) {
                setDetailAndScroll();
            }
        },

        actionViewPersonalData: function () {
            this.createView('viewPersonalData', 'views/personal-data/modals/personal-data', {
                model: this.model
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'erase', function () {
                    this.clearView('viewPersonalData');
                    this.model.fetch();
                }, this);
            });
        },

        afterSave: function () {
            if (this.isNew) {
                this.notify('Created', 'success');
            } else {
                this.notify('Saved', 'success');
            }
            this.enableButtons();
            this.setIsNotChanged();
        },

        beforeSave: function () {
            this.notify('Saving...');
        },

        beforeBeforeSave: function () {
            this.disableButtons();

            this.model.trigger('before:save');
        },

        afterSaveError: function () {
            this.enableButtons();

            if (this.fetchOnModelAfterSaveError) {
                this.model.fetch();
            }
        },

        afterNotModified: function () {
            var msg = this.translate('notModified', 'messages');
            Espo.Ui.warning(msg, 'warning');
            this.enableButtons();
        },

        afterNotValid: function () {
            this.notify(this.translate('Record cannot be saved'), 'error');
            this.enableButtons();
        },

        errorHandlerDuplicate: function (duplicates) {
            this.notify(false);
            this.createView('duplicate', 'views/modals/duplicate', {
                scope: this.entityType,
                duplicates: duplicates,
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'save', function () {
                    this.model.set('forceDuplicate', true);
                    this.actionSave();
                }.bind(this));

            }.bind(this));
        },

        setReadOnly: function () {
            if (!this.readOnlyLocked) {
                this.readOnly = true;
            }

            var bottomView = this.getView('bottom');
            if (bottomView && 'setReadOnly' in bottomView) {
                bottomView.setReadOnly();
            }

            this.getFieldList().forEach(function (field) {
                this.setFieldReadOnly(field);
            }, this);
        },

        setNotReadOnly: function (onlyNotSetAsReadOnly) {
            if (!this.readOnlyLocked) {
                this.readOnly = false;
            }

            var bottomView = this.getView('bottom');
            if (bottomView && 'setNotReadOnly' in bottomView) {
                bottomView.setNotReadOnly();
            }

            this.getFieldList().forEach(function (field) {
                if (onlyNotSetAsReadOnly) {
                    if (this.recordHelper.getFieldStateParam(field, 'readOnly')) return;
                }
                this.setFieldNotReadOnly(field);
            }, this);
        },

        manageAccessEdit: function (second) {
            if (this.isNew) return;

            var editAccess = this.getAcl().checkModel(this.model, 'edit', true);

            if (!editAccess || this.readOnlyLocked) {
                this.readOnly = true;
                this.hideActionItem('edit');
                if (this.duplicateAction) {
                    this.hideActionItem('duplicate');
                }
                if (this.selfAssignAction) {
                    this.hideActionItem('selfAssign');
                }
            } else {
                this.showActionItem('edit');
                if (this.duplicateAction) {
                    this.showActionItem('duplicate');
                }
                if (this.selfAssignAction) {
                    this.hideActionItem('selfAssign');
                    if (this.model.has('assignedUserId')) {
                        if (!this.model.get('assignedUserId')) {
                            this.showActionItem('selfAssign');
                        }
                    }
                }
                if (!this.readOnlyLocked) {
                    if (this.readOnly && second) {
                        this.setNotReadOnly(true);
                    }
                    this.readOnly = false;
                }
            }

            if (editAccess === null) {
                this.listenToOnce(this.model, 'sync', function () {
                    this.manageAccessEdit(true);
                }, this);
            }
        },

        manageAccessDelete: function () {
            if (this.isNew) return;

            var deleteAccess = this.getAcl().checkModel(this.model, 'delete', true);

            if (!deleteAccess) {
                this.hideActionItem('delete');
            } else {
                this.showActionItem('delete');
            }

            if (deleteAccess === null) {
                this.listenToOnce(this.model, 'sync', function () {
                    this.manageAccessDelete();
                }, this);
            }
        },

        manageAccess: function () {
            this.manageAccessEdit();
            this.manageAccessDelete();
        },

        addButton: function (o) {
            var name = o.name;
            if (!name) return;
            for (var i in this.buttonList) {
                if (this.buttonList[i].name == name) {
                    return;
                }
            }
            this.buttonList.push(o);
        },

        addDropdownItem: function (o) {
            var name = o.name;
            if (!name) return;
            for (var i in this.dropdownItemList) {
                if (this.dropdownItemList[i].name == name) {
                    return;
                }
            }
            this.dropdownItemList.push(o);
        },

        enableButtons: function () {
            $(".page-header .detail-button-container .action").removeAttr('disabled').removeClass('disabled');
            $(".page-header .detail-button-container .dropdown-toggle").removeAttr('disabled').removeClass('disabled');
        },

        disableButtons: function () {
            $(".page-header .detail-button-container .action").attr('disabled', 'disabled').addClass('disabled');
            $(".page-header .detail-button-container .dropdown-toggle").attr('disabled', 'disabled').addClass('disabled');
        },

        removeButton: function (name) {
            for (var i in this.buttonList) {
                if (this.buttonList[i].name == name) {
                    this.buttonList.splice(i, 1);
                    break;
                }
            }
            for (var i in this.dropdownItemList) {
                if (this.dropdownItemList[i].name == name) {
                    this.dropdownItemList.splice(i, 1);
                    break;
                }
            }

            if (this.isRendered()) {
                window.dispatchEvent(new CustomEvent('record:buttons-update', { detail: this.getRecordButtons() }));
            }
        },

        getRecordButtons() {
            if (this.buttonsDisabled) {
                return null;
            }

            return {
                buttons: this.buttonList,
                editButtons: this.buttonEditList,
                dropdownButtons: this.dropdownItemList,
                dropdownEditButtons: this.dropdownEditItemList,
                additionalButtons: this.additionalButtons,
                additionalEditButtons: this.additionalEditButtons,
            }
        },

        convertDetailLayout: function (simplifiedLayout, el) {
            var layout = [];

            el = el ?? (this.options.el || '#' + (this.id));

            for (var p in simplifiedLayout) {
                var panel = {};
                panel.label = this.getLanguage().translate(simplifiedLayout[p].label, 'labels', this.scope) || null;
                if ('customLabel' in simplifiedLayout[p]) {
                    panel.customLabel = simplifiedLayout[p].customLabel;
                }
                if (simplifiedLayout[p].isAttributePanel) {
                    panel.isAttributePanel = true;
                }
                panel.name = simplifiedLayout[p].name || 'panel-' + p.toString();
                panel.style = simplifiedLayout[p].style || 'default';
                panel.rows = [];

                for (var i in simplifiedLayout[p].rows) {
                    var row = [];

                    for (var j in simplifiedLayout[p].rows[i]) {
                        var cellDefs = simplifiedLayout[p].rows[i][j];

                        if (cellDefs == false) {
                            row.push(false);
                            continue;
                        }

                        if (!cellDefs.name) {
                            continue;
                        }

                        let name = cellDefs.name;
                        let fieldModel, type, viewName;

                        // remove relation virtual fields
                        let parts = name.split('__');
                        let relEntity = null;
                        if (parts.length === 2) {
                            if (this.model.relationModel) {
                                relEntity = this.model.relationModel.name;
                            }

                            if (relEntity !== parts[0]) {
                                continue;
                            }
                            fieldModel = this.model.relationModel
                            type = cellDefs.type || fieldModel.getFieldType(parts[1]) || 'base';
                            viewName = cellDefs.view || fieldModel.getFieldParam(parts[1], 'view') || this.getFieldManager().getViewName(type);
                        } else {
                            fieldModel = this.model
                            type = cellDefs.type || fieldModel.getFieldType(name) || 'base';
                            viewName = cellDefs.view || fieldModel.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);
                        }


                        var o = {
                            el: el + '  .field[data-name="' + name + '"]',
                            defs: {
                                name: name,
                                params: cellDefs.params || {}
                            },
                            mode: this.fieldsMode
                        };

                        if (parts.length === 2) {
                            o.useRelationModel = true
                            o.defs.name = parts[1]
                            if (!cellDefs.customLabel) {
                                cellDefs.customLabel = this.translate(o.defs.name, 'fields', relEntity) + ' (Relation)'
                            }
                        }

                        if (this.readOnly) {
                            o.readOnly = true;
                        }

                        if (cellDefs.readOnly) {
                            o.readOnly = true;
                            o.readOnlyLocked = true;
                        }

                        if (this.readOnlyLocked) {
                            o.readOnlyLocked = true;
                        }

                        if (this.inlineEditDisabled || cellDefs.inlineEditDisabled) {
                            o.inlineEditDisabled = true;
                        }

                        var fullWidth = cellDefs.fullWidth || false;
                        if (!fullWidth) {
                            if (simplifiedLayout[p].rows[i].length == 1) {
                                fullWidth = true;
                            }
                        }

                        if (this.recordHelper.getFieldStateParam(name, 'hidden')) {
                            o.disabled = true;
                        }
                        if (this.recordHelper.getFieldStateParam(name, 'hiddenLocked')) {
                            o.disabledLocked = true;
                        }
                        if (this.recordHelper.getFieldStateParam(name, 'readOnly')) {
                            o.readOnly = true;
                        }
                        if (!o.readOnlyLocked && this.recordHelper.getFieldStateParam(name, 'readOnlyLocked')) {
                            o.readOnlyLocked = true;
                        }
                        if (this.recordHelper.getFieldStateParam(name, 'required') !== null) {
                            o.defs.params = o.defs.params || {};
                            o.defs.params.required = this.recordHelper.getFieldStateParam(name, 'required');
                        }
                        if (this.recordHelper.hasFieldOptionList(name)) {
                            o.customOptionList = this.recordHelper.getFieldOptionList(name);
                        }

                        if (this.recordHelper.hasFieldDisabledOptionList(name)) {
                            o.disabledOptionList = this.recordHelper.getFieldDisabledOptionList(name)
                        }

                        var cell = {
                            name: name + 'Field',
                            view: viewName,
                            field: name,
                            el: el + '  .field[data-name="' + name + '"]',
                            fullWidth: fullWidth,
                            options: o
                        };

                        if ('customLabel' in cellDefs) {
                            cell.customLabel = cellDefs.customLabel;
                        }
                        if ('customCode' in cellDefs) {
                            cell.customCode = cellDefs.customCode;
                        }
                        if ('noLabel' in cellDefs) {
                            cell.noLabel = cellDefs.noLabel;
                        }
                        if ('span' in cellDefs) {
                            cell.span = cellDefs.span;
                        }

                        row.push(cell);
                    }

                    panel.rows.push(row);
                }
                layout.push(panel);
            }

            if (typeof this.loadListOptionsDataFromLayout == 'function') {
                this.loadListOptionsDataFromLayout(layout)
            }

            return this.prepareLayoutAfterConverting(layout);
        },

        loadListOptionsDataFromLayout(layout) {
            const idsToLoad = []

            layout.forEach(panel => {
                (panel.rows || []).forEach(row => {
                    (row || []).forEach(cell => {
                        if (cell && cell.field) {
                            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', cell.field, 'extensibleEnumId']) || this.model.getFieldParam(cell.field, 'extensibleEnumId');
                            if (extensibleEnumId &&
                                (this.model.getFieldParam(cell.field, 'dropdown') || this.model.get('attributesDefs')?.[cell.field]?.dropdown) &&
                                !idsToLoad.includes(extensibleEnumId) &&
                                !Espo['extensible_enum_' + extensibleEnumId]) {
                                idsToLoad.push(extensibleEnumId)
                            }
                        }
                    })
                })
            })

            if (idsToLoad.length === 0) {
                return
            }

            this.ajaxGetRequest(`ExtensibleEnum/action/getExtensibleEnumsOptions`, { extensibleEnumIds: idsToLoad }, { async: false }).then(res => {
                Object.entries(res).forEach(([extensibleEnumId, value]) => {
                    Espo['extensible_enum_' + extensibleEnumId] = value;
                })
            });
        },

        getRelEntity(entityType, link) {
            let relationName = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'relationName']);
            if (relationName) {
                return relationName.charAt(0).toUpperCase() + relationName.slice(1);
            }

            return null;
        },

        prepareLayoutAfterConverting(layout) {
            return layout;
        },

        getGridLayout: function (callback) {
            if (this.gridLayout !== null) {
                callback(this.gridLayout);
                return;
            }

            var gridLayoutType = this.gridLayoutType || 'record';

            if (this.detailLayout) {
                this.gridLayout = {
                    type: gridLayoutType,
                    layout: this.convertDetailLayout(this.detailLayout)
                };
                callback(this.gridLayout);
                return;
            }

            this._helper.layoutManager.get(this.model.name, this.layoutName, this.options.layoutRelatedScope ?? null, function (data) {
                data = Espo.Utils.clone(data)
                this.prepareLayoutData(data);
                this.layoutData = data
                this.gridLayout = {
                    type: gridLayoutType,
                    layout: this.convertDetailLayout(data.layout)
                };
                callback(this.gridLayout);
            }.bind(this));
        },

        getUserLanguages() {
            const user = this.getUser()
            let disabledLanguages = user.get('disabledLanguages') || []
            let userLocale = this.getConfig().get('locales')[user.get('localeId')]
            if (!userLocale) {
                userLocale = this.getConfig().get('locales')[this.getConfig().get('locale')]
            }

            const systemLanguages = this.getConfig().get('inputLanguageList')
            let mainLocaleCode = ''
            for (const [code, language] of Object.entries(this.getConfig().get('referenceData').Language ?? {})) {
                if (language.role === 'main') {
                    mainLocaleCode = code
                }
            }

            systemLanguages.push(mainLocaleCode)
            let languages = systemLanguages.filter(item => disabledLanguages.indexOf(item) === -1);

            if (userLocale && systemLanguages.includes(userLocale.language)) {
                languages.unshift(userLocale.language)
            } else {
                languages.unshift(mainLocaleCode)
            }

            // remove duplicates
            languages = languages.filter((item, index) => languages.indexOf(item) === index)
            const result = []
            languages.forEach(code => {
                if (systemLanguages.includes(code)) {
                    if (code === mainLocaleCode) {
                        result.push('')
                    } else {
                        result.push(code.split('_').map(part => Espo.utils.upperCaseFirst(part.toLowerCase())).join(''))
                    }
                }
            })

            if (result.length === 0) {
                result.push('')
            }

            return result
        },

        putAttributesToModel() {
            if (this.layoutName !== 'detail' || !this.getMetadata().get(`scopes.${this.model.name}.hasAttribute`) || !this.getAcl().check(this.model.name, 'read') || this.model.isNew()) {
                return;
            }

            $.each(this.model.defs.fields, (name, defs) => {
                if (defs.attributeId) {
                    delete this.model.defs.fields[name];
                }
            })

            let attributesDefs = this.model.get('attributesDefs') || {};

            // prepare composited attributes
            $.each(attributesDefs, (name, defs) => {
                if (defs.type === 'composite') {
                    (defs.childrenIds || []).forEach(attributeId => {
                        $.each(attributesDefs, (name1, defs1) => {
                            if (defs1.attributeId === attributeId) {
                                attributesDefs[name1]['compositedField'] = true;
                            }
                        });
                    })
                }
            });

            $.each(attributesDefs, (name, defs) => {
                this.model.defs['fields'][name] = defs;
            });
        },

        sortBySortOrder(jsonObj) {
            const sortedEntries = Object.entries(jsonObj).sort(([, a], [, b]) => a.sortOrder - b.sortOrder);
            return Object.fromEntries(sortedEntries);
        },

        sortAttributeDefs(originalAttributeDefs) {
            let attributesDefs = {};

            let attributeGroups = {};

            $.each(originalAttributeDefs, (name, defs) => {
                if (!defs.attributeGroup?.id) {
                    attributesDefs[name] = defs;
                } else {
                    attributeGroups[defs.attributeGroup.id] = defs.attributeGroup;
                }
            })

            attributeGroups = this.sortBySortOrder(attributeGroups);

            $.each(attributeGroups, (id, group) => {
                let groupItems = {};
                $.each(originalAttributeDefs, (name, defs) => {
                    if (defs.attributeGroup?.id === id) {
                        groupItems[name] = defs;
                    }
                });
                const sortedEntries = Object.entries(groupItems).sort(([, a], [, b]) => a.sortOrderInAttributeGroup - b.sortOrderInAttributeGroup);
                $.each(Object.fromEntries(sortedEntries), (name, defs) => {
                    attributesDefs[name] = defs;
                });
            })

            return attributesDefs;
        },

        prepareLayoutData(data) {
            if (this.layoutName === 'detail' && this.getMetadata().get(`scopes.${this.model.name}.hasAttribute`) && this.getAcl().check(this.model.name, 'read')) {
                let attributePanels = {};
                $.each((this.getConfig().get('referenceData')?.AttributePanel || {}), (code, panel) => {
                    attributePanels[panel.id] = panel;
                    attributePanels[panel.id]['layoutRows'] = [];
                    attributePanels[panel.id]['layoutRow'] = [];
                });

                attributePanels = this.sortBySortOrder(attributePanels);

                let addedGroups = {};

                const pushItem = (name, defs) => {
                    let panelId = defs.attributePanelId;
                    let attributeGroupId = defs.attributeGroup?.id || null;

                    if (!attributePanels[panelId]) {
                        // skip item when panel do not exist
                        return
                    }

                    if ((this.getAcl().getScopeForbiddenFieldList(this.model.name, 'read') || []).includes(name)) {
                        return
                    }

                    // put attribute group item
                    if (attributeGroupId) {
                        if (!addedGroups[panelId]) {
                            addedGroups[panelId] = {};
                        }

                        if (!addedGroups[panelId][attributeGroupId]) {
                            addedGroups[panelId][attributeGroupId] = true;
                            attributePanels[panelId].layoutRow.push({
                                name: panelId + '_' + attributeGroupId,
                                customLabel: defs.attributeGroup.name,
                                view: "views/attribute/fields/attribute-group-layout-item",
                                fullWidth: true
                            });
                            attributePanels[panelId].layoutRows.push(attributePanels[panelId].layoutRow);
                            attributePanels[panelId].layoutRow = [];
                        }
                    }

                    let item = {
                        name: name,
                        customLabel: defs.detailViewLabel || defs.label,
                        fullWidth: defs.fullWidth
                    }
                    if (defs.layoutDetailView) {
                        item.view = defs.layoutDetailView;
                    }

                    attributePanels[panelId].layoutRow.push(item);
                    if (attributePanels[panelId].layoutRow[0]['fullWidth'] || attributePanels[panelId]['layoutRow'][1]) {
                        attributePanels[panelId].layoutRows.push(attributePanels[panelId].layoutRow);
                        attributePanels[panelId].layoutRow = [];
                    }
                }

                if (!this.model.isNew()) {
                    this.putAttributesToModel();
                    $.each(this.sortAttributeDefs(this.model.get('attributesDefs') || {}), (name, defs) => {
                        if (!defs.layoutDetailDisabled && !defs.compositedField) {
                            if (defs.multilangField) {
                                return
                            }

                            if (defs.isMultilang) {
                                this.getUserLanguages().forEach(code => {
                                    pushItem(name + code, this.model.get('attributesDefs')[name + code])
                                })
                                return;
                            }

                            pushItem(name, defs)
                        }
                    });
                }

                $.each(attributePanels, (id, item) => {
                    if (item.layoutRow.length > 0) {
                        attributePanels[id].layoutRow.push(false);
                        attributePanels[id].layoutRows.push(item.layoutRow);
                    }
                })

                $.each(attributePanels, (id, item) => {
                    data.layout.forEach((row, k) => {
                        if (row.id === id) {
                            delete data.layout[k];
                        }
                    })
                })

                let languageCode = null;
                $.each((this.getConfig().get('referenceData')?.Locale || {}), (code, item) => {
                    if (item.id === this.getUser().get('localeId')) {
                        languageCode = item.languageCode;
                    }
                })

                let labelName = 'name';
                if (languageCode) {
                    labelName += languageCode.split('_').map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase()).join('');
                }

                let preferences = this.getPreferences().get('closedPanelOptions') ?? {};
                let scopePreferences = preferences[this.scope] ?? {}
                let closedAttributePanels = scopePreferences['closedAttributePanels'] ?? []

                $.each(attributePanels, (id, item) => {
                    if (item.layoutRows.length > 0) {
                        const panel = {
                            id: id,
                            name: id,
                            isAttributePanel: true,
                            label: item[labelName] || item.name,
                            rows: item.layoutRows
                        }

                        if (closedAttributePanels.includes(id)) {
                            panel.rows = []
                            this.recordHelper.setPanelStateParam(id, 'hidden', true)
                        } else {
                            this.recordHelper.setPanelStateParam(id, 'hidden', false)
                        }

                        data.layout.push(panel);
                    }
                })
            }
        },

        layoutHasActionFields() {
            if (this.getMetadata().get(['scopes', this.model.name, 'actionDisabled'])) {
                return false;
            }
            if (!this.model.id) {
                return false;
            }
            const fieldActions = this.getMetadata().get(['clientDefs', this.scope, 'dynamicFieldActions']) || []
            let layoutHasActionFields = false

            if (fieldActions.length && this.gridLayout) {
                const fields = fieldActions.map(action => action.displayField);
                this.gridLayout.layout.forEach(panel => {
                    panel.rows.forEach(row => {
                        row.forEach(cell => {
                            if (cell && fields.includes(cell.field)) {
                                layoutHasActionFields = true;
                            }
                        })
                    })
                })
            }

            return layoutHasActionFields;
        },

        fetchDynamicFieldActions(callback) {
            this.model.fetchDynamicActions('field')
                .then(actions => {
                    this.dynamicFieldActions = actions;
                    callback();
                })
        },

        createMiddleView: function (callback) {
            var el = this.options.el || '#' + (this.id);
            this.waitForView('middle');
            this.getGridLayout(function (layout) {

                const createView = () => {
                    this.createView('middle', this.middleView, {
                        model: this.model,
                        scope: this.scope,
                        type: this.type,
                        _layout: layout,
                        el: el + ' .middle',
                        layoutData: {
                            model: this.model,
                            columnCount: this.columnCount
                        },
                        recordHelper: this.recordHelper,
                        recordViewObject: this
                    }, callback);
                }

                if (this.layoutHasActionFields()) {
                    this.fetchDynamicFieldActions(createView);
                } else {
                    createView();
                }
            }.bind(this));
        },

        createBottomView: function (callback) {
            var el = this.options.el || '#' + (this.id);
            this.createView('bottom', this.bottomView, {
                model: this.model,
                scope: this.scope,
                el: el + ' .bottom',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                isInSmallView: this.isSmall,
                listInlineEditModeEnabled: this.listInlineEditModeEnabled
            }, view => {
                this.listenToOnce(view, 'after:render', () => {
                    this.trigger('detailPanelsLoaded', { list: this.getMiddlePanels().concat(view.panelList) });
                })
                if (callback) {
                    callback(view)
                }
            });
        },

        getMiddlePanels() {
            let middlePanels = [];
            if (this.gridLayout && 'layout' in this.gridLayout) {
                Object.values(this.gridLayout.layout).forEach(panel => {
                    let name = panel.label || panel.customLabel;

                    if (name) {
                        middlePanels.push({ title: name, name: panel.name, isAttributePanel: panel.isAttributePanel });
                    }
                });
            }
            return middlePanels
        },

        build: function (callback) {

            if (this.middleView) {
                this.createMiddleView(callback);
            }

            if (!this.bottomDisabled && this.bottomView) {
                this.createBottomView();
            }
        },

        exitAfterCreate: function () {
            if (this.model.id) {
                var url = '#' + this.scope + '/view/' + this.model.id;

                this.getRouter().navigate(url, { trigger: false });
                this.getRouter().dispatch(this.scope, 'view', {
                    id: this.model.id,
                    rootUrl: this.options.rootUrl
                });
                return true;
            }
        }, /**
         * Called after save or cancel.
         * By default redirects page. Can be orverriden in options.
         * @param {String} after Name of action (save, cancel, etc.) after which #exit is invoked.
         */
        exit: function (after) {
            if (after) {
                var methodName = 'exitAfter' + Espo.Utils.upperCaseFirst(after);
                if (methodName in this) {
                    var result = this[methodName]();
                    if (result) {
                        return;
                    }
                }
            }

            var url;
            if (this.returnUrl) {
                url = this.returnUrl;
            } else {
                if (after == 'delete') {
                    url = this.options.rootUrl || '#' + this.scope;
                    this.getRouter().navigate(url, { trigger: false });
                    this.getRouter().dispatch(this.scope, null, {
                        isReturn: true
                    });
                    return;
                }
                if (this.model.id) {
                    url = '#' + this.scope + '/view/' + this.model.id;

                    if (!this.returnDispatchParams) {
                        this.getRouter().navigate(url, { trigger: false });
                        var options = {
                            id: this.model.id,
                            model: this.model
                        };
                        if (this.options.rootUrl) {
                            options.rootUrl = this.options.rootUrl;
                        }
                        this.getRouter().dispatch(this.scope, 'view', options);
                    }
                } else {
                    url = this.options.rootUrl || '#' + this.scope;
                }
            }

            if (this.returnDispatchParams) {
                var controller = this.returnDispatchParams.controller;
                var action = this.returnDispatchParams.action;
                var options = this.returnDispatchParams.options || {};
                this.getRouter().navigate(url, { trigger: false });
                this.getRouter().dispatch(controller, action, options);
                return;
            }

            this.getRouter().navigate(url, { trigger: true });
        },

        actionCopyConfigurations() {
            let select = [];
            let disabledFields = this.getMetadata().get(['scopes', this.scope, 'disabledFieldsForCopyConfigurations'], []);
            let linksFields = [];
            Object.entries(this.getMetadata().get(['entityDefs', this.scope, 'fields'])).forEach(([field, fieldDefs]) => {
                if (['createdBy', 'modifiedBy', 'teams', 'assignedAccounts', 'assignedUser', 'ownerUser'].includes(field)) {
                    return true;
                }

                if (fieldDefs['exportDisabled']) {
                    return true;
                }

                if (!disabledFields.includes(field) && fieldDefs['type'] !== 'file') {
                    select.push(field);
                }

                if (['link', 'file'].includes(fieldDefs['type']) && !disabledFields.includes(field + 'Id')) {
                    select.push(field + 'Id');
                }

                if (fieldDefs['type'] === 'linkMultiple' && !disabledFields.includes(field + 'Ids')) {
                    select.push(field + 'Ids');
                }

                if (['link', 'linkMultiple'].includes(fieldDefs['type'])) {
                    linksFields.push(field);
                }
            });

            this.notify(this.translate('pleaseWait', 'messages'));

            this.ajaxGetRequest(this.scope, {
                select: select.join(','),
                where: [
                    {
                        type: "equals",
                        attribute: "id",
                        value: this.model.get('id')
                    }
                ]
            }).then(data => {
                let cleanseItem = (item) => {
                    Object.entries(item).forEach(([key, value]) => {
                        if (!value || ['createdById', 'modifiedById', 'modifiedAt', 'createdAt', 'assignedUserId', 'ownerUserId'].includes(key)) {
                            delete item[key];
                            return true;
                        }
                    });
                }

                if (data.list && data.list.length) {
                    let item = data.list[0];
                    let configurations = [{
                        entity: this.scope,
                        payload: item
                    }];

                    linksFields.forEach((link) => {
                        let linkData = item[link];
                        delete item[link];

                        if (_.isEmpty(linkData)) {
                            return true;
                        }

                        let linkDefs = this.getMetadata().get(['entityDefs', this.scope, 'links', link]);

                        if (!linkDefs['entity']) {
                            return true;
                        }

                        if (linkDefs['type'] === 'belongsTo') {
                            cleanseItem(linkData);
                            configurations.unshift({
                                entity: linkDefs['entity'],
                                payload: linkData
                            });
                        }

                        if (linkDefs['type'] === 'hasMany') {
                            linkData.forEach(subItem => {
                                cleanseItem(subItem);
                                configurations.push({
                                    entity: linkDefs['entity'],
                                    payload: subItem
                                });
                            });
                        }
                    });

                    this.copyToClipboard(JSON.stringify(configurations), (copied) => {
                        if (copied) {
                            this.notify(this.translate('Done'), 'success');
                        } else {
                            this.notify('Error copying text to clipboard', 'danger');
                        }
                    })

                }
            })
        },

        isTreeAllowed() {
            return true;
        },


        onTreePanelRendered(view) {
            this.listenTo(this.model, 'after:save', () => {
                if (window.treePanelComponent) {
                    window.treePanelComponent.rebuildTree();
                }
            });
            this.listenTo(this.model, 'after:relate after:unrelate after:dragDrop', link => {
                if (['parents', 'children'].includes(link)) {
                    if (window.treePanelComponent) {
                        window.treePanelComponent.rebuildTree();
                    }
                }
            });
        },

        selectNode(data) {
            if (data.scope === this.scope) {
                window.location.href = `/#${this.scope}/view/${data.id}`;
            } else {
                this.getStorage().set('selectedNodeId', this.scope, data.id);
                this.getStorage().set('selectedNodeRoute', this.scope, data.route);
                window.location.href = `/#${this.scope}`;
            }
        },

        onTreeResize(width) {
        },

        actionDynamicActionPreviewTemplate(data) {
            const defs = (this.getMetadata().get(['clientDefs', this.entityType, 'dynamicRecordActions']) || []).find(defs => defs.id === data.id)
            if (!defs) {
                return;
            }
            let path = defs.actionViewPath;

            let o = {
                previewTemplateId: defs.id,
                label: defs.name
            };
            (defs.optionsToPass || []).forEach((option) => {
                if (option in this) {
                    o[option] = this[option];
                }
            });

            this.createView(defs.id, path, o, (view) => {
                if (typeof view[defs.action] === 'function') {
                    view[defs.action]();
                }
            });
        },

        canLoadActivities: function () {
            let streamAllowed = this.model
                ? this.getAcl().checkModel(this.model, 'stream', true)
                : this.getAcl().check(this.scope, 'stream');
            return !this.getMetadata().get('scopes.' + this.scope + '.streamDisabled') && streamAllowed
        },

        getSvelteSideViewProps(parentView) {
            const loadInsights = () => {
                parentView.createView('rightSideView', parentView.rightSideView, {
                    el: parentView.options.el + ' .right-side-view .insights',
                    scope: this.scope,
                    mode: this.mode,
                    model: this.model,
                    recordHelper: this.recordHelper,
                }, view => {
                    this.listenTo(view, 'after:render', () => {
                        if (this.mode === 'edit') {
                            view.setEditMode();
                        } else {
                            view.setDetailMode()
                        }
                    });

                    view.render();

                    this.listenTo(this.model, 'sync', () => {
                        view.reRender();
                    });

                        if (this.getMetadata().get(['scopes', this.model.name, 'layouts']) && this.getUser().isAdmin() && this.mode === 'detail') {
                            parentView.createView('insightsLayoutConfigurator', "views/record/layout-configurator", {
                                scope: this.scope,
                                viewType: 'insights',
                                layoutData: view.layoutData,
                                el: $(`${parentView.options.el} .right-side-view .layout-editor-container`).get(0),
                            }, (v) => {
                                v.on("refresh", () => {
                                    loadInsights()
                                })
                                v.render()
                            })
                        }

                });
            }

            return {
                scope: this.scope,
                model: this.model,
                id: this.model.id,
                mode: this.mode,
                hasStream: this.canLoadActivities() && !!this.model.id,
                showInsights: ['edit', 'detail'].includes(this.mode),
                createView: parentView.createView.bind(this),
                isCollapsed: !['edit', 'detail'].includes(this.mode),
                loadInsights: loadInsights,
                loadActivities: (callback) => {
                    let el = parentView.options.el + ' .right-side-view .activities'
                    parentView.createView('activities', 'views/record/activities', {
                        el: el,
                        model: this.model,
                        mode: this.mode,
                        scope: this.scope,
                        recordHelper: this.recordHelper,
                        recordViewObject: this.recordViewObject
                    }, view => {
                        if (callback) {
                            callback(view);
                        }
                        view.render();
                    })
                },
                fetchModel: () => {
                    this.model.fetch();
                }
            }
        }
    });
});
