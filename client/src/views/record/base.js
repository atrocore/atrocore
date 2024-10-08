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

Espo.define('views/record/base', ['view', 'view-record-helper', 'ui-handler', 'lib!Twig'], function (Dep, ViewRecordHelper, UiHandler) {

    return Dep.extend({

        type: 'edit',

        fieldsMode: 'edit',

        entityType: null,

        scope: null,

        isNew: false,

        dependencyDefs: {},

        uiHandlerDefs: [],

        fieldList: null,

        mode: null,

        getConfirmMessage: function (_prev, attrs, model) {
            if (model._confirmMessage) {
                return model._confirmMessage;
            }

            let confirmMessage = null;
            if (this.model.get('id')) {
                let confirmations = this.getMetadata().get(`clientDefs.${model.urlRoot}.confirm`) || {};
                $.each(confirmations, (field, data) => {
                    if (_prev[field] !== attrs[field]) {
                        let key = null;
                        if (typeof data.values !== 'undefined') {
                            data.values.forEach(value => {
                                if (attrs[field] === value) {
                                    key = data.message;
                                }
                            });
                        } else {
                            key = data;
                        }

                        if (key) {
                            let parts = key.split('.');
                            confirmMessage = this.translate(parts[2], parts[1], parts[0]);
                        }
                    }
                });
            }

            return confirmMessage;
        },

        hideField: function (name, locked) {
            this.recordHelper.setFieldStateParam(name, 'hidden', true);
            if (locked) {
                this.recordHelper.setFieldStateParam(name, 'hiddenLocked', true);
            }

            var processHtml = function () {
                var fieldView = this.getFieldView(name);

                if (fieldView) {
                    var $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.addClass('hidden');
                    $label.addClass('hidden');
                    $cell.addClass('hidden-cell');
                } else {
                    this.$el.find('.cell[data-name="' + name + '"]').addClass('hidden-cell');
                    this.$el.find('.field[data-name="' + name + '"]').addClass('hidden');
                    this.$el.find('label.control-label[data-name="' + name + '"]').addClass('hidden');
                }
            }.bind(this);
            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                view.setDisabled(locked);
            }
        },

        showField: function (name) {
            if (this.recordHelper.getFieldStateParam(name, 'hiddenLocked')) {
                return;
            }
            this.recordHelper.setFieldStateParam(name, 'hidden', false);

            var processHtml = function () {
                var fieldView = this.getFieldView(name);

                if (fieldView) {
                    var $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.removeClass('hidden');
                    $label.removeClass('hidden');
                    $cell.removeClass('hidden-cell');
                } else {
                    this.$el.find('.cell[data-name="' + name + '"]').removeClass('hidden-cell');
                    this.$el.find('.field[data-name="' + name + '"]').removeClass('hidden');
                    this.$el.find('label.control-label[data-name="' + name + '"]').removeClass('hidden');
                }
            }.bind(this);

            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                if (!view.disabledLocked) {
                    view.setNotDisabled();
                }
            }
        },

        setFieldReadOnly: function (name, locked) {
            this.recordHelper.setFieldStateParam(name, 'readOnly', true);
            if (locked) {
                this.recordHelper.setFieldStateParam(name, 'readOnlyLocked', true);
            }

            var view = this.getFieldView(name);
            if (view) {
                view.setReadOnly(locked);
            }
        },

        setFieldNotReadOnly: function (name) {
            this.recordHelper.setFieldStateParam(name, 'readOnly', false);

            var view = this.getFieldView(name);
            if (view) {
                if (view.readOnly) {
                    view.setNotReadOnly();
                    if (this.mode == 'edit') {
                        if (!view.readOnlyLocked && view.mode == 'detail') {
                            view.setMode('edit');
                            if (view.isRendered()) {
                                view.reRender();
                            }
                        }
                    }
                }
            }
        },

        setFieldRequired: function (name) {
            this.recordHelper.setFieldStateParam(name, 'required', true);

            var view = this.getFieldView(name);
            if (view) {
                view.setRequired();
            }
        },

        setFieldNotRequired: function (name) {
            this.recordHelper.setFieldStateParam(name, 'required', false);

            var view = this.getFieldView(name);
            if (view) {
                view.setNotRequired();
            }
        },

        setFieldAddDisabledOptions: function (name, list) {
            this.recordHelper.setFieldAddDisabledOptions(name, list);
            var view = this.getFieldView(name);

            if (view) {
                if ('disableOptions' in view) {
                    view.disableOptions(this.recordHelper.getFieldDisabledOptionList(name));
                }
            }
        },

        setFieldRemoveDisabledOptions: function (name, list) {
            this.recordHelper.setFieldRemoveDisabledOptions(name, list);

            var view = this.getFieldView(name);
            if (view) {
                if ('disableOptions' in view) {
                    view.disableOptions(this.recordHelper.getFieldDisabledOptionList(name));
                }
            }
        },

        setFieldOptionList: function (name, list) {
            this.recordHelper.setFieldOptionList(name, list);

            var view = this.getFieldView(name);
            if (view) {
                if ('setOptionList' in view) {
                    view.setOptionList(list);
                }
            }
        },

        resetFieldOptionList: function (name) {
            this.recordHelper.clearFieldOptionList(name);

            var view = this.getFieldView(name);
            if (view) {
                if ('resetOptionList' in view) {
                    view.resetOptionList();
                }
            }
        },

        showPanel: function (name) {
            this.recordHelper.setPanelStateParam(name, 'hidden', false);
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="' + name + '"]').removeClass('hidden');
            }
        },

        hidePanel: function (name) {
            this.recordHelper.setPanelStateParam(name, 'hidden', true);
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="' + name + '"]').addClass('hidden');
            }
        },

        setConfirmLeaveOut: function (value) {
            this.getRouter().confirmLeaveOut = value;
        },

        getFieldViews: function () {
            var fields = {};
            this.fieldList.forEach(function (item) {
                var view = this.getFieldView(item);
                if (view) {
                    fields[item] = view;
                }
            }, this);
            return fields;
        },

        getFields: function () {
            return this.getFieldViews();
        },

        getFieldView: function (name) {
            var view = this.getView(name + 'Field') || null;

            // TODO remove
            if (!view) {
                view = this.getView(name) || null;
            }
            return view;
        },

        getField: function (name) {
            return this.getFieldView(name);
        },

        getFieldList: function () {
            var fieldViews = this.getFieldViews();
            return Object.keys(fieldViews);
        },

        data: function () {
            return {
                scope: this.scope,
                entityType: this.entityType,
                hiddenPanels: this.recordHelper.getHiddenPanels(),
                hiddenFields: this.recordHelper.getHiddenFields()
            };
        },

        // TODO remove
        handleDataBeforeRender: function (data) {
            this.getFieldList().forEach(function (field) {
                var viewKey = field + 'Field';
                data[field] = data[viewKey];
            }, this);
        },

        setup: function () {
            if (typeof this.model === 'undefined') {
                throw new Error('Model has not been injected into record view.');
            }

            this.recordHelper = new ViewRecordHelper();

            this.once('remove', function () {
                if (this.isChanged) {
                    this.resetModelChanges();
                }
                this.setIsNotChanged();
            }, this);

            this.events = this.events || {};

            this.entityType = this.model.name;
            this.scope = this.options.scope || this.entityType;

            this.fieldList = this.options.fieldList || this.fieldList || [];

            this.numId = Math.floor((Math.random() * 10000) + 1);
            this.id = Espo.Utils.toDom(this.entityType) + '-' + Espo.Utils.toDom(this.type) + '-' + this.numId;

            if (this.model.isNew()) {
                this.isNew = true;
            }

            this.setupBeforeFinal();
        },

        setupBeforeFinal: function () {
            this.attributes = this.model.getClonedAttributes();

            this.listenTo(this.model, 'change', function () {
                if (this.mode == 'edit') {
                    this.setIsChanged();
                }
            }, this);

            if (this.options.attributes) {
                this.model.set(this.options.attributes);
            }

            this.listenTo(this.model, 'sync', function () {
                this.attributes = this.model.getClonedAttributes();
            }, this);

            this.initDependancy();
            this.initUiHandler();
        },

        setInitalAttributeValue: function (attribute, value) {
            this.attributes[attribute] = value;
        },

        checkAttributeIsChanged: function (name) {
            return !_.isEqual(this.attributes[name], this.model.get(name));
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

        initUiHandler: function () {
            this.uiHandlerDefs = Espo.Utils.clone(this.uiHandlerDefs || []);
            this.uiHandler = new UiHandler(this.uiHandlerDefs, this, Twig);

            this.processUiHandler('onLoad', this.name);
            this.listenTo(this.model, 'changeField', fieldName => {
                this.processUiHandler('onChange', fieldName);
            });
            this.listenTo(this.model, 'focusField', fieldName => {
                this.processUiHandler('onFocus', fieldName);
            });
        },

        processUiHandler: function (type, field) {
            this.uiHandler.process(type, field);
        },

        initDependancy: function () {
            Object.keys(this.dependencyDefs || {}).forEach(function (attr) {
                this.listenTo(this.model, 'change:' + attr, function () {
                    this._handleDependencyAttribute(attr);
                }, this);
            }, this);
            this._handleDependencyAttributes();
        },

        setupFieldLevelSecurity: function () {
            let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.entityType, 'read');
            forbiddenFieldList.forEach(function (field) {
                this.hideField(field, true);
            }, this);

            let readOnlyFieldList = this.getAcl().getScopeForbiddenFieldList(this.entityType, 'edit'),
                linksList = this.getMetadata().get(['entityDefs', this.scope, 'links']) || {};

            Object.keys(linksList).forEach((link) => {
                if (!readOnlyFieldList.includes(link) && linksList[link].entity && linksList[link].type && linksList[link].type === 'hasMany') {
                    let setReadOnly = false;

                    if (this.getAcl().check(linksList[link].entity, 'read')) {
                        if (linksList[link].relationName) {
                            let relationName = linksList[link].relationName.charAt(0).toUpperCase() + linksList[link].relationName.slice(1);

                            if (!this.getAcl().check(relationName, 'create') || !this.getAcl().check(relationName, 'delete')) {
                                setReadOnly = true;
                            }
                        }
                    } else {
                        setReadOnly = true;
                    }

                    if (setReadOnly) {
                        readOnlyFieldList.push(link);
                    }
                }
            });

            readOnlyFieldList.forEach(function (field) {
                this.setFieldReadOnly(field, true);
            }, this);
        },

        setIsChanged: function () {
            this.isChanged = true;
        },

        setIsNotChanged: function () {
            this.isChanged = false;
        },

        validate: function () {
            let notValid = false;

            $.each(this.getFields(), (name, fieldData) => {
                if (fieldData.mode === 'edit') {
                    if (!fieldData.disabled && !fieldData.readOnly && !fieldData.$el.hasClass('hidden')) {
                        notValid = fieldData.validate() || notValid;
                    }
                }
            });

            return notValid
        },

        afterSave: function () {
            if (this.isNew) {
                this.notify('Created', 'success');
            } else {
                this.notify('Saved', 'success');
            }
            this.setIsNotChanged();
        },

        beforeBeforeSave: function () {

        },

        beforeSave: function () {
            this.notify('Saving...');
        },

        afterSaveError: function () {
        },

        afterNotModified: function () {
            var msg = this.translate('notModified', 'messages');
            Espo.Ui.warning(msg, 'warning');
            this.setIsNotChanged();
        },

        afterNotValid: function () {
            this.notify('Not valid', 'error');
        },

        getDataForSave: function () {
            let data = this.fetch();
            let model = this.model;

            $.each(model.getClonedAttributes(), (name, value) => {
                if (!(name in data) && !this.getMetadata().get(['entityDefs', model.urlRoot, 'fields', name, 'relationVirtualField'])) {
                    data[name] = value;
                }
            });

            return data;
        },

        save: function (callback, skipExit) {
            this.beforeBeforeSave();

            var data = this.getDataForSave();

            var self = this;
            var model = this.model;

            var initialAttributes = this.attributes;

            var beforeSaveAttributes = this.model.getClonedAttributes();

            var attrs = false;
            if (model.isNew()) {
                attrs = data;
            } else {
                for (var name in data) {
                    if (_.isEqual(initialAttributes[name], data[name])) {
                        continue;
                    }
                    (attrs || (attrs = {}))[name] = data[name];
                }
            }

            if (!attrs) {
                this.trigger('cancel:save');
                this.afterNotModified();
                return true;
            }

            model.set(attrs, {silent: true});

            if (this.validate()) {
                model.attributes = beforeSaveAttributes;
                this.trigger('cancel:save');
                this.afterNotValid();
                return;
            }

            let hashParts = window.location.hash.split('/view/');
            if (typeof hashParts[1] !== 'undefined' && this.model.defs._relationName) {
                attrs._relationName = this.model.defs._relationName;
                attrs._relationEntity = hashParts[0].replace('#', '');
                attrs._relationEntityId = hashParts[1];

                $.each(this.model.defs.fields, (field, fieldDefs) => {
                    if (fieldDefs.relId && model.get(field)) {
                        attrs._relationId = model.get(field);
                    }
                });

                // @todo remove it soon
                attrs._mainEntityId = hashParts[1];
            }

            let _prev = {};
            $.each(attrs, function (field, value) {
                _prev[field] = initialAttributes[field];
            });

            attrs['_prev'] = _prev;
            attrs['_silentMode'] = true;

            this.beforeSave();

            this.trigger('before:save', attrs);
            model.trigger('before:save', attrs);

            this.notify(false);

            let confirmMessage = this.getConfirmMessage(_prev, attrs, model);
            if (confirmMessage) {
                Espo.Ui.confirm(confirmMessage, {
                    confirmText: self.translate('Apply'),
                    cancelText: self.translate('Cancel'),
                    cancelCallback() {
                        self.enableButtons();
                        self.trigger('cancel:save');
                    }
                }, (result) => {
                    this.saveModel(model, callback, skipExit, attrs);
                });
            } else {
                this.saveModel(model, callback, skipExit, attrs);
            }

            return true;
        },

        saveModel(model, callback, skipExit, attrs) {
            this.notify('Saving...');
            let self = this;

            let isNew = this.isNew;
            let method = 'ajaxPostRequest';
            let url = model.urlRoot;

            if (!isNew) {
                method = 'ajaxPatchRequest';
                url += '/' + model.get('id');
            }

            this[method](url, attrs).success(function (res) {
                // set to model
                model.set(res);

                this.attributes = model.getClonedAttributes();
                self.afterSave();
                self.trigger('after:save');
                model.trigger('after:save');
                if (!callback) {
                    if (!skipExit) {
                        if (isNew) {
                            self.exit('create');
                        } else {
                            self.exit('save');
                        }
                    }
                } else {
                    callback(self);
                }
            }).error(function (xhr) {
                let statusReason = xhr.responseText || '';
                xhr.errorIsHandled = true;
                if (xhr.status === 409) {
                    self.notify(false);
                    self.enableButtons();
                    self.trigger('cancel:save');
                    Espo.Ui.confirm(statusReason, {
                        confirmText: self.translate('Apply'),
                        cancelText: self.translate('Cancel')
                    }, function () {
                        attrs['_prev'] = null;
                        attrs['_ignoreConflict'] = true;
                        attrs['_silentMode'] = false;
                        self.saveModel(model, callback, skipExit, attrs);
                    })
                } else {
                    self.enableButtons();
                    self.trigger('cancel:save');
                    if (xhr.status === 304) {
                        Espo.Ui.notify(self.translate('notModified', 'messages'), 'warning', 1000 * 60 * 60 * 2, true);
                    } else {
                        Espo.Ui.notify(`${self.translate("Error")} ${xhr.status}: ${statusReason}`, "error", 1000 * 60 * 60 * 2, true);
                    }
                }
            });
        },

        fetch: function () {
            var data = {};
            var fieldViews = this.getFieldViews();
            for (var i in fieldViews) {
                var view = fieldViews[i];
                if (view.mode == 'edit') {
                    if (!view.disabled && !view.readOnly && view.isFullyRendered()) {
                        _.extend(data, view.fetch());
                    }
                }
            }
            return data;
        },

        populateDefaults: function () {
            this.model.populateDefaults();

            var defaultHash = {};
            var defaultTeamId = this.getUser().get('defaultTeamId');
            if (defaultTeamId) {
                if (this.model.hasField('teams') && !this.model.getFieldParam('teams', 'default')) {
                    defaultHash['teamsIds'] = [defaultTeamId];
                    defaultHash['teamsNames'] = {};
                    defaultHash['teamsNames'][defaultTeamId] = this.getUser().get('defaultTeamName');
                }
            }

            for (var attr in defaultHash) {
                if (this.model.has(attr)) {
                    delete defaultHash[attr];
                }
            }

            this.model.set(defaultHash, {silent: true});
        },

        errorHandlerDuplicate: function (duplicates) {
        },

        _handleDependencyAttributes: function () {
            Object.keys(this.dependencyDefs || {}).forEach(function (attr) {
                this._handleDependencyAttribute(attr);
            }, this);
        },

        _handleDependencyAttribute: function (attr) {
            var data = this.dependencyDefs[attr];
            var value = this.model.get(attr);
            if (value in (data.map || {})) {
                (data.map[value] || []).forEach(function (item) {
                    this._doDependencyAction(item);
                }, this);
            } else {
                if ('default' in data) {
                    (data.default || []).forEach(function (item) {
                        this._doDependencyAction(item);
                    }, this);
                }
            }
        },

        _doDependencyAction: function (data) {
            var action = data.action;

            var methodName = 'dependencyAction' + Espo.Utils.upperCaseFirst(action);
            if (methodName in this && typeof this.methodName == 'function') {
                this.methodName(data);
                return;
            }

            var fieldList = data.fieldList || data.fields || [];
            var panelList = data.panelList || data.panels || [];

            switch (action) {
                case 'hide':
                    panelList.forEach(function (item) {
                        this.hidePanel(item);
                    }, this);
                    fieldList.forEach(function (item) {
                        this.hideField(item);
                    }, this);
                    break;
                case 'show':
                    panelList.forEach(function (item) {
                        this.showPanel(item);
                    }, this);
                    fieldList.forEach(function (item) {
                        this.showField(item);
                    }, this);
                    break;
                case 'setRequired':
                    fieldList.forEach(function (field) {
                        this.setFieldRequired(field);
                    }, this);
                    break;
                case 'setNotRequired':
                    fieldList.forEach(function (field) {
                        this.setFieldNotRequired(field);
                    }, this);
                    break;
                case 'setReadOnly':
                    fieldList.forEach(function (field) {
                        this.setFieldReadOnly(field);
                    }, this);
                    break;
                case 'setNotReadOnly':
                    fieldList.forEach(function (field) {
                        this.setFieldNotReadOnly(field);
                    }, this);
                    break;
            }
        },

        createField: function (name, view, params, mode, readOnly, options) {
            var o = {
                model: this.model,
                mode: mode || 'edit',
                el: this.options.el + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    params: params || {}
                }
            };
            if (readOnly) {
                o.readOnly = true;
            }

            view = view || this.model.getFieldParam(name, 'view');

            if (!view) {
                var type = this.model.getFieldType(name) || 'base';
                view = this.getFieldManager().getViewName(type);
            }

            if (options) {
                for (var param in options) {
                    o[param] = options[param];
                }
            }

            if (this.recordHelper.getFieldStateParam(name, 'hidden')) {
                o.disabled = true;
            }
            if (this.recordHelper.getFieldStateParam(name, 'readOnly')) {
                o.readOnly = true;
            }
            if (this.recordHelper.getFieldStateParam(name, 'required') !== null) {
                o.defs.params.required = this.recordHelper.getFieldStateParam(name, 'required');
            }
            if (this.recordHelper.hasFieldOptionList(name)) {
                o.customOptionList = this.recordHelper.getFieldOptionList(name);
            }
            if (this.recordHelper.hasFieldDisabledOptionList(name)) {
                o.disabledOptionList = this.recordHelper.getFieldDisabledOptionList(name)
            }

            var viewKey = name + 'Field';

            this.createView(viewKey, view, o);

            if (!~this.fieldList.indexOf(name)) {
                this.fieldList.push(name);
            }
        },

        exit: function (after) {
        }

    });

});
