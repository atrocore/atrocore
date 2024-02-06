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

Espo.define('views/admin/field-manager/edit', ['view', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'admin/field-manager/edit',

        entityTypeWithTranslatedOptionsList: ['enum', 'multiEnum', 'array'],

        scriptTypeFields: ['script', 'preview'],

        paramWithTooltipList: ['audited', 'required', 'default', 'min', 'max', 'maxLength', 'after', 'before', 'readOnly'],

        data: function () {
            return {
                scope: this.scope,
                field: this.field,
                defs: this.defs,
                paramList: this.paramList,
                type: this.type,
                fieldList: this.fieldList,
                isCustom: this.defs.isCustom,
                isNew: this.isNew,
                hasDynamicLogicPanel: this.hasDynamicLogicPanel
            };
        },

        events: {
            'click button[data-action="close"]': function () {
                this.getRouter().navigate('#Admin/fieldManager/scope=' + this.scope, {trigger: true});
            },
            'click button[data-action="save"]': function () {
                this.save();
            },
            'click button[data-action="resetToDefault"]': function () {
                this.resetToDefault();
            }
        },

        setupFieldData: function (callback) {
            this.defs = {};
            this.fieldList = [];

            this.model = new Model();
            this.model.name = 'Admin';
            this.model.urlRoot = 'Admin/fieldManager/' + this.scope;

            this.model.defs = {
                fields: {
                    name: {required: true, maxLength: 100},
                    label: {required: true},
                    tooltipText: {},
                    tooltipLink: {}
                }
            };

            if (!this.isNew) {
                this.model.id = this.field;
                this.model.scope = this.scope;
                this.model.set('name', this.field);

                if (this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'tooltip'])) {
                    const tooltipTextRaw = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'tooltipText']);
                    const tooltipLink = this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'tooltipLink']);
                    const tooltipText = this.getLanguage().translate(this.field, 'tooltips', this.scope);
                    this.model.set('tooltipText', tooltipTextRaw ? tooltipText : null);
                    this.model.set('tooltipLink', tooltipLink || null);
                }
            } else {
                this.model.set('type', this.type);
            }

            this.listenTo(this.model, 'change:readOnly', function () {
                this.readOnlyControl();
            }, this);

            this.listenTo(this.model, 'change:isMultilang', function () {
                if (this.model.get('type') === 'script' && this.getConfig().get('isMultilangActive')) {
                    if (this.model.get('isMultilang')) {
                        (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                            this.scriptTypeFields.forEach(field => {
                                let params =  this.paramList.find(item => item.name === field);

                                if (params) {
                                    let multiLangParams = _.clone(params);

                                    multiLangParams.language = locale;
                                    multiLangParams.name += locale.charAt(0).toUpperCase() + locale.charAt(1) + locale.charAt(3) + locale.charAt(4).toLowerCase();

                                    if (this.paramList.findIndex(item => item.name === multiLangParams.name) === -1) {
                                        this.paramList.push(multiLangParams);

                                        this.createFieldView(multiLangParams.type, multiLangParams.name, null, multiLangParams);
                                    }
                                }
                            });
                        });

                        this.reRender();
                    } else {
                        (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                            this.scriptTypeFields.forEach(field => {
                                let multilangField = field + locale.charAt(0).toUpperCase() + locale.charAt(1) + locale.charAt(3) + locale.charAt(4).toLowerCase(),
                                    index = this.paramList.findIndex(item => item.name === multilangField);
                                if (index !== -1) {
                                    let view = this.getView(multilangField);
                                    if (view) {
                                        view.hide();
                                        this.model.set(multilangField, "");
                                    }
                                }
                            });
                        });
                    }
                }
            }, this);

            var hasRequired = false;

            this.getModelFactory().create(this.scope, function (model) {
                if (!this.isNew) {
                    this.type = model.getFieldType(this.field);
                }

                if (
                    this.getMetadata().get(['scopes', this.scope, 'hasPersonalData'])
                    &&
                    this.getMetadata().get(['fields', this.type, 'personalData'])
                ) {
                    this.hasPersonalData = true;
                }

                Promise.race([
                    new Promise(function (resolve) {
                        if (this.isNew) {
                            resolve();
                        }
                    }.bind(this)),
                    new Promise(function (resolve) {
                        if (this.isNew) return;
                        this.ajaxGetRequest('Admin/fieldManager/' + this.scope + '/' + this.field).then(function (data) {
                            this.defs = data;
                            resolve();
                        }.bind(this));
                    }.bind(this))
                ]).then(function () {
                    let field = this.field;
                    if (this.defs.multilangField) {
                        field = this.defs.multilangField;
                    }

                    if (!this.isNew) {
                        let labelField = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'labelField']) ?? field;
                        this.model.set('label', this.getLanguage().translate(labelField, 'fields', this.scope));
                        (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                            this.setLocaleLabel(locale, labelField);
                        });
                    }

                    this.paramList = [];
                    var paramList = Espo.Utils.clone(this.getFieldManager().getParams(this.type) || []);

                    if (!this.isNew) {
                        (this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'fieldManagerAdditionalParamList']) || []).forEach(function (item) {
                            paramList.push(item);
                        }, this);
                    }

                    paramList.forEach(function (o) {
                        var item = o.name;
                        if (item === 'required') {
                            hasRequired = true;
                        }
                        var disableParamName = 'customization' + Espo.Utils.upperCaseFirst(item) + 'Disabled';
                        if (this.getMetadata().get('entityDefs.' + this.scope + '.fields.' + this.field + '.' + disableParamName)) {
                            return;
                        }

                        let skipParam = false;
                        (this.defs.hideParams || []).forEach(function (name) {
                            if (o.name === name) {
                                skipParam = true;
                            }
                        });

                        if (!skipParam) {
                            this.paramList.push(o);
                        }
                    }, this);

                    if (this.hasPersonalData) {
                        this.paramList.push({
                            name: 'isPersonalData',
                            type: 'bool'
                        });
                    }
                    if(this.type === 'linkMultiple') {
                        this.paramList.push(
                            {
                                name: "auditedLink",
                                type: "bool"
                            }
                        );
                        this.model.set('auditedLink', this.getMetadata().get(['entityDefs', this.scope, 'links', this.field, 'audited']));
                    }
                    this.paramList.forEach(function (o) {
                        this.model.defs.fields[o.name] = o;
                    }, this);

                    this.model.set(this.defs);

                    if (this.isNew) {
                        this.model.populateDefaults();
                    }

                    this.createFieldView('varchar', 'name', !this.isNew, {
                        trim: true
                    });
                    this.createFieldView('varchar', 'label', null, {
                        trim: true
                    });

                    this.createFieldView('text', 'tooltipText', null, {
                        trim: true,
                        rows: 1
                    });

                    this.createFieldView('url', 'tooltipLink', null, {
                        trim: true,
                        rows: 1
                    });

                    if (this.hasPersonalData) {
                        this.createFieldView('bool', 'isPersonalData', null, {});
                    }

                    this.createFieldView('text', 'tooltipText', null, {
                        trim: true,
                        rows: 1
                    });

                    this.hasDynamicLogicPanel = false;
                    if (
                        !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'disabled'])
                        &&
                        !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dynamicLogicDisabled'])
                        &&
                        !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'layoutDetailDisabled'])
                    ) {
                        if (!this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dynamicLogicVisibleDisabled'])) {
                            this.model.set('dynamicLogicVisible', this.getMetadata().get(['clientDefs', this.scope, 'dynamicLogic', 'fields', this.field, 'visible']));
                            this.createFieldView(null, 'dynamicLogicVisible', null, {
                                view: 'views/admin/field-manager/fields/dynamic-logic-conditions',
                                scope: this.scope
                            });
                            this.hasDynamicLogicPanel = true;
                        }
                        if (
                            !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dynamicLogicRequiredDisabled'])
                            &&
                            !this.getMetadata().get(['fields', this.type, 'readOnly'])
                            &&
                            hasRequired
                        ) {
                            this.model.set('dynamicLogicRequired', this.getMetadata().get(['clientDefs', this.scope, 'dynamicLogic', 'fields', this.field, 'required']));
                            this.createFieldView(null, 'dynamicLogicRequired', null, {
                                view: 'views/admin/field-manager/fields/dynamic-logic-conditions',
                                scope: this.scope
                            });
                            this.hasDynamicLogicPanel = true;
                        }
                        if (
                            !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dynamicLogicReadOnlyDisabled'])
                            &&
                            !this.getMetadata().get(['fields', this.type, 'readOnly'])
                        ) {
                            this.model.set('dynamicLogicReadOnly', this.getMetadata().get(['clientDefs', this.scope, 'dynamicLogic', 'fields', this.field, 'readOnly']));
                            this.createFieldView(null, 'dynamicLogicReadOnly', null, {
                                view: 'views/admin/field-manager/fields/dynamic-logic-conditions',
                                scope: this.scope
                            });
                            this.hasDynamicLogicPanel = true;
                        }

                        if (
                            ~['enum', 'array', 'multiEnum'].indexOf(this.type)
                            &&
                            !this.getMetadata().get(['entityDefs', this.scope, 'fields', this.field, 'dynamicLogicOptionsDisabled'])
                        ) {
                            this.model.set('dynamicLogicOptions', this.getMetadata().get(['clientDefs', this.scope, 'dynamicLogic', 'options', this.field]));
                            this.createFieldView(null, 'dynamicLogicOptions', null, {
                                view: 'views/admin/field-manager/fields/dynamic-logic-options',
                                scope: this.scope
                            });
                            this.hasDynamicLogicPanel = true;
                        };
                    }

                    this.model.fetchedAttributes = this.model.getClonedAttributes();
                    this.paramList.forEach(function (o) {
                        if (o.hidden) {
                            return;
                        }
                        var options = {};
                        if (o.tooltip || ~this.paramWithTooltipList.indexOf(o.name)) {
                            options.tooltip = true;
                            options.tooltipText = this.translate(o.name, 'tooltips', 'FieldManager');
                            options.tooltipLink = this.translate(o.name, 'tooltipLink', 'FieldManager');
                        }
                        this.createFieldView(o.type, o.name, null, o, options);
                    }, this);

                    callback();

                }.bind(this));
            }.bind(this));
        },

        setup: function () {
            this.scope = this.options.scope;
            this.field = this.options.field;
            this.type = this.options.type;

            this.isNew = false;
            if (!this.field) {
                this.isNew = true;
            }

            this.wait(true);
            this.setupFieldData(function () {
                this.wait(false);
            }.bind(this));
        },

        setLocaleLabel: function (locale, field) {
            let name = 'label' + locale.charAt(0).toUpperCase() + locale.charAt(1) + locale.charAt(3) + locale.charAt(4).toLowerCase();
            this.ajaxGetRequest(`I18n?locale=${locale}`).then(responseData => {
                if (responseData && responseData[this.scope] && responseData[this.scope]['fields'] && responseData[this.scope]['fields'][field]) {
                    this.model.set(name, responseData[this.scope]['fields'][field]);
                } else {
                    this.model.set(name, this.model.get('label'));
                }
            });
        },

        afterRender: function () {
            this.getView('name').on('change', function (m) {
                var name = this.model.get('name');
                var label = name;
                if (label.length) {
                    label = label.charAt(0).toUpperCase() + label.slice(1);
                }
                this.model.set('label', label);
                if (name) {
                    name = name.replace(/-/g, '').replace(/_/g, '').replace(/[^\w\s]/gi, '').replace(/ (.)/g, function(match, g) {
                        return g.toUpperCase();
                    }).replace(' ', '');
                    if (name.length) {
                        name = name.charAt(0).toLowerCase() + name.slice(1);
                    }
                }
                this.model.set('name', name);
            }, this);

            if (this.defs.multilangField) {
                this.hideLabelsExcept(this.defs.multilangLocale);
            }
        },

        hideLabelsExcept: function (locale) {
            this.hideField('label');
            (this.getConfig().get('inputLanguageList') || []).forEach(v => {
                let name = 'label' + v.charAt(0).toUpperCase() + v.charAt(1) + v.charAt(3) + v.charAt(4).toLowerCase();
                if (locale !== v) {
                    this.hideField(name);
                }
            });
        },

        readOnlyControl: function () {
            if (this.model.get('readOnly')) {
                this.hideField('dynamicLogicReadOnly');
                this.hideField('dynamicLogicRequired');
                this.hideField('dynamicLogicOptions');
            } else {
                this.showField('dynamicLogicReadOnly');
                this.showField('dynamicLogicRequired');
                this.showField('dynamicLogicOptions');
            }
        },

        hideField: function (name) {
            var f = function () {
                var view = this.getView(name)
                if (view) {
                    this.$el.find('.cell[data-name="'+name+'"]').addClass('hidden');
                    view.setDisabled();
                }
            }.bind(this);
            if (this.isRendered()) {
                f();
            } else {
                this.once('after:render', f);
            }
        },

        showField: function (name) {
            var f = function () {
                var view = this.getView(name)
                if (view) {
                    this.$el.find('.cell[data-name="'+name+'"]').removeClass('hidden');
                    view.setNotDisabled();
                }
            }.bind(this);
            if (this.isRendered()) {
                f();
            } else {
                this.once('after:render', f);
            }
        },

        createFieldView: function (type, name, readOnly, params, options, callback) {
            var viewName = (params || {}).view || this.getFieldManager().getViewName(type);

            var o = {
                model: this.model,
                el: this.options.el + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    params: params
                },
                mode: readOnly ? 'detail' : 'edit',
                readOnly: readOnly,
                scope: this.scope,
                field: this.field,
            };
            _.extend(o, options || {});

            this.createView(name, viewName, o, callback);
            this.fieldList.push(name);
        },

        disableButtons: function () {
            this.$el.find('[data-action="save"]').attr('disabled', 'disabled').addClass('disabled');
            this.$el.find('[data-action="resetToDefault"]').attr('disabled', 'disabled').addClass('disabled');
        },

        enableButtons: function () {
            this.$el.find('[data-action="save"]').removeAttr('disabled').removeClass('disabled');
            this.$el.find('[data-action="resetToDefault"]').removeAttr('disabled').removeClass('disabled');
        },

        getTooltipStatus: function () {
            return (this.model.get('tooltipText') && this.model.get('tooltipText') !== '') ||
                (this.model.get('tooltipLink') && this.model.get('tooltipLink') !== '');
        },

        save: function () {
            this.disableButtons();

            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                if (!view.readOnly) {
                    view.fetchToModel();
                }
            }, this);

            var notValid = false;
            this.fieldList.forEach(function (field) {
                notValid = this.getView(field).validate() || notValid;
            }, this);

            if (notValid) {
                this.notify('Not valid', 'error');

                this.enableButtons();
                return;
            }

            this.model.set('tooltip', this.getTooltipStatus());

            this.listenToOnce(this.model, 'sync', function () {
                Espo.Ui.notify(false);
                this.enableButtons();

                this.updateLanguage();

                Promise.all([
                    new Promise(function (resolve) {
                        this.getMetadata().load(function () {
                            this.getMetadata().storeToCache();
                            resolve();
                        }.bind(this), true);
                    }.bind(this)),
                    new Promise(function (resolve) {
                        this.getLanguage().load(function () {
                            resolve();
                        }, true);
                    }.bind(this))
                ]).then(function () {
                    this.trigger('after:save');
                }.bind(this));

                this.model.fetchedAttributes = this.model.getClonedAttributes();
            }, this);

            this.notify('Saving...');

            this.fieldList.forEach((field) => {
                const fieldView = this.getView(field);
                if (fieldView && typeof fieldView.beforeSave !== "undefined") {
                    fieldView.beforeSave();
                }
            });

            if (this.isNew) {
                this.model.save().error(function () {
                    this.enableButtons();
                }.bind(this));
            } else {
                var attributes = this.model.getClonedAttributes();

                if (this.model.fetchedAttributes.label === attributes.label) {
                    delete attributes.label;
                }

                if (this.model.fetchedAttributes.tooltipText === attributes.tooltipText || !this.model.fetchedAttributes.tooltipText && !attributes.tooltipText) {
                    delete attributes.tooltipText;
                }

                if (this.model.fetchedAttributes.tooltipLink === attributes.tooltipLink || !this.model.fetchedAttributes.tooltipLink && !attributes.tooltipLink) {
                    delete attributes.tooltipLink;
                }

                if ('translatedOptions' in attributes) {
                    if (_.isEqual(this.model.fetchedAttributes.translatedOptions, attributes.translatedOptions)) {
                        delete attributes.translatedOptions;
                    }
                }

                this.model.save(attributes, {patch: true}).error(function () {
                    this.enableButtons();
                }.bind(this));
            }
        },

        updateLanguage: function () {
            var langData = this.getLanguage().data;
            if (this.scope in langData) {
                if (!('fields' in langData[this.scope])) {
                    langData[this.scope]['fields'] = {};
                }
                langData[this.scope]['fields'][this.model.get('name')] = this.model.get('label');

                if (!('tooltips' in langData[this.scope])) {
                    langData[this.scope]['tooltips'] = {};
                }
                langData[this.scope]['tooltips'][this.model.get('name')] = this.model.get('tooltipText');
                if (!langData[this.scope]['tooltipLink']) {
                    langData[this.scope]['tooltipLink'] = {};
                }
                langData[this.scope]['tooltipLink'][this.model.get('name')] = this.model.get('tooltipLink');

                if (this.getMetadata().get(['fields', this.model.get('type'), 'translatedOptions']) && this.model.get('translatedOptions')) {
                    langData[this.scope].options = langData[this.scope].options || {};
                    langData[this.scope]['options'][this.model.get('name')] = this.model.get('translatedOptions') || {};
                }
            }
        },

        resetToDefault: function () {
            this.confirm(this.translate('confirmation', 'messages'), function () {

                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                this.ajaxPostRequest('FieldManager/action/resetToDefault', {
                    scope: this.scope,
                    name: this.field
                }).then(function () {
                    Promise.all([
                        new Promise(function (resolve) {
                            this.getMetadata().load(function () {
                                this.getMetadata().storeToCache();
                                resolve();
                            }.bind(this), true);
                        }.bind(this)),
                        new Promise(function (resolve) {
                            this.getLanguage().load(function () {
                                resolve();
                            }.bind(this), true);
                        }.bind(this))
                    ]).then(function () {
                        this.setupFieldData(function () {
                            this.notify('Done', 'success');
                            this.reRender();
                        }.bind(this));
                    }.bind(this));
                }.bind(this));

            }, this);
        }

    });

});
