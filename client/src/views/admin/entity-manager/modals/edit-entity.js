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

Espo.define('views/admin/entity-manager/modals/edit-entity', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        cssName: 'edit-entity',

        template: 'admin/entity-manager/modals/edit-entity',

        data: function () {
            let scopeData = this.getMetadata().get('scopes.' + this.scope);
            return {
                isNew: this.isNew,
                additionalParamsLayout: this.getMetadata().get('app.additionalEntityParams.layout') || [],
                isActiveUnavailable: this.getMetadata().get(['scopes', this.scope, 'isActiveUnavailable']) || false,
                auditable: scopeData && scopeData.object && scopeData.customizable && !['Relation', 'ReferenceData'].includes(scopeData.type)
            };
        },

        setupData: function () {
            var scope = this.scope;

            this.hasColorField = !this.getConfig().get('scopeColorsDisabled');

            this.model.set('type', 'Base');

            if (scope) {
                this.model.set('id', scope);
                this.model.set('name', scope);
                this.model.set('labelSingular', this.translate(scope, 'scopeNames'));
                this.model.set('labelPlural', this.translate(scope, 'scopeNamesPlural'));
                this.model.set('type', this.getMetadata().get('scopes.' + scope + '.type') || '');
                this.model.set('disabled', this.getMetadata().get('scopes.' + scope + '.disabled') || false);
                this.model.set('streamDisabled', this.getMetadata().get('scopes.' + scope + '.streamDisabled') || false);

                this.model.set('sortBy', this.getMetadata().get('entityDefs.' + scope + '.collection.sortBy'));
                this.model.set('sortDirection', this.getMetadata().get('entityDefs.' + scope + '.collection.asc') ? 'asc' : 'desc');

                this.model.set('textFilterFields', this.getMetadata().get(['entityDefs', scope, 'collection', 'textFilterFields']) || []);

                this.model.set('statusField', this.getMetadata().get('scopes.' + scope + '.statusField') || null);

                if (this.hasColorField) {
                    this.model.set('color', this.getMetadata().get(['clientDefs', scope, 'color']) || null);
                }

                this.model.set('iconClass', this.getMetadata().get(['clientDefs', scope, 'iconClass']) || null);

                this.model.set('kanbanViewMode', this.getMetadata().get(['clientDefs', scope, 'kanbanViewMode']) || false);
                this.model.set('kanbanStatusIgnoreList', this.getMetadata().get(['scopes', scope, 'kanbanStatusIgnoreList']) || []);

                this.model.set('hasDeleteWithoutConfirmation', this.getMetadata().get(['scopes', scope, 'type']) !== 'ReferenceData');
                this.model.set('deleteWithoutConfirmation', this.getMetadata().get(['scopes', scope, 'deleteWithoutConfirmation']) ||  false);

                this.model.set('hasModifiedExtendedRelations', this.getMetadata().get(['scopes', scope, 'type']) !== 'ReferenceData');
                this.model.set('hasDuplicatableRelations', this.getMetadata().get(['scopes', scope, 'type']) !== 'ReferenceData');
            }
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'danger'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            var scope = this.scope = this.options.scope || false;

            var header = 'Create Entity';
            this.isNew = true;
            if (scope) {
                header = 'Edit Entity';
                this.isNew = false;
            }

            this.header = this.translate(header, 'labels', 'Admin');

            var model = this.model = new Model();
            model.name = 'EntityManager';

            if (!this.isNew) {
                this.isCustom = this.getMetadata().get(['scopes', scope, 'isCustom'])
            }

            if (!this.isNew && !this.isCustom) {
                this.buttonList.push({
                    name: 'resetToDefault',
                    text: this.translate('Reset to Default', 'labels', 'Admin')
                });
            }

            this.setupData();

            let entityTypes = this.getMetadata().get('app.entityTypes') || [];

            this.createView('type', 'views/fields/enum', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="type"]',
                defs: {
                    name: 'type',
                    params: {
                        required: true,
                        options: entityTypes
                    }
                },
                readOnly: !this.isNew
            });

            this.createView('disabled', 'views/fields/bool', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="disabled"]',
                defs: {
                    name: 'disabled'
                },
                tooltip: true,
                tooltipText: this.translate('disabled', 'tooltips', 'EntityManager'),
                tooltipLink: this.translate('disabled', 'tooltipLink', 'EntityManager')
            });

            this.createView('streamDisabled', 'views/fields/bool', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="streamDisabled"]',
                defs: {
                    name: 'streamDisabled'
                }
            });

            this.createView('name', 'views/fields/varchar', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="name"]',
                defs: {
                    name: 'name',
                    params: {
                        required: true,
                        trim: true,
                        maxLength: 100
                    }
                },
                readOnly: scope != false
            });
            this.createView('labelSingular', 'views/fields/varchar', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="labelSingular"]',
                defs: {
                    name: 'labelSingular',
                    params: {
                        required: true,
                        trim: true
                    }
                }
            });
            this.createView('labelPlural', 'views/fields/varchar', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="labelPlural"]',
                defs: {
                    name: 'labelPlural',
                    params: {
                        required: true,
                        trim: true
                    }
                }
            });

            if (this.hasColorField) {
                this.createView('color', 'views/fields/colorpicker', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="color"]',
                    defs: {
                        name: 'color'
                    }
                });
            }

            this.createView('iconClass', 'views/admin/entity-manager/fields/icon-class', {
                model: model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="iconClass"]',
                defs: {
                    name: 'iconClass'
                }
            });

            if (scope) {
                var fieldDefs = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};

                var orderableFieldList = Object.keys(fieldDefs).filter(function (item) {
                    if (fieldDefs[item].notStorable) {
                        return false;
                    }
                    return true;
                }, this).sort(function (v1, v2) {
                    return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
                }.bind(this));

                var translatedOptions = {};
                orderableFieldList.forEach(function (item) {
                    translatedOptions[item] = this.translate(item, 'fields', scope);
                }, this);

                this.createView('sortBy', 'views/fields/enum', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="sortBy"]',
                    defs: {
                        name: 'sortBy',
                        params: {
                            options: orderableFieldList
                        }
                    },
                    translatedOptions: translatedOptions
                });

                this.createView('sortDirection', 'views/fields/enum', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="sortDirection"]',
                    defs: {
                        name: 'sortDirection',
                        params: {
                            options: ['asc', 'desc']
                        }
                    }
                });

                this.createView('kanbanViewMode', 'views/fields/bool', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="kanbanViewMode"]',
                    defs: {
                        name: 'kanbanViewMode'
                    }
                });

                var optionList = Object.keys(fieldDefs).filter(function (item) {
                    var fieldType = fieldDefs[item].type;
                    if (!this.getMetadata().get(['fields', fieldType, 'textFilter'])) return false
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'disabled'])) {
                        return false;
                    }
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'notStorable'])) {
                        return false;
                    }
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'emHidden'])) {
                        return false;
                    }
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'textFilterDisabled'])) {
                        return false;
                    }
                    return true;
                }, this);

                var textFilterFieldsTranslation = {};
                optionList.forEach(function (item) {
                    textFilterFieldsTranslation[item] = this.translate(item, 'fields', scope);
                }, this);

                optionList.unshift('id');
                textFilterFieldsTranslation['id'] = this.translate('id', 'fields');

                this.createView('textFilterFields', 'views/fields/multi-enum', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="textFilterFields"]',
                    defs: {
                        name: 'textFilterFields',
                        params: {
                            options: optionList
                        }
                    },
                    tooltip: true,
                    tooltipText: this.translate('textFilterFields', 'tooltips', 'EntityManager'),
                    tooltipLink: this.translate('textFilterFields', 'tooltipLink', 'EntityManager'),
                    translatedOptions: textFilterFieldsTranslation
                });


                var enumFieldList = Object.keys(fieldDefs).filter(function (item) {
                    if (fieldDefs[item].disabled) return;
                    if (fieldDefs[item].notStorable && fieldDefs[item].notStorable === true) return;
                    if (fieldDefs[item].type == 'enum') {
                        return true;
                    }
                    return;
                }, this).sort(function (v1, v2) {
                    return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
                }.bind(this));

                var translatedStatusFields = {};
                enumFieldList.forEach(function (item) {
                    translatedStatusFields[item] = this.translate(item, 'fields', scope);
                }, this);
                enumFieldList.unshift('');
                translatedStatusFields[''] = '-' + this.translate('None') + '-';

                this.createView('statusField', 'views/fields/enum', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="statusField"]',
                    defs: {
                        name: 'statusField',
                        params: {
                            options: enumFieldList
                        }
                    },
                    tooltip: true,
                    tooltipText: this.translate('statusField', 'tooltips', 'EntityManager'),
                    tooltipLink: this.translate('statusField', 'tooltipLink', 'EntityManager'),
                    translatedOptions: translatedStatusFields
                });

                var statusOptionList = [];
                var translatedStatusOptions = {};

                this.createView('kanbanStatusIgnoreList', 'views/fields/multi-enum', {
                    model: model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="kanbanStatusIgnoreList"]',
                    defs: {
                        name: 'kanbanStatusIgnoreList',
                        params: {
                            options: statusOptionList
                        }
                    },
                    translatedOptions: translatedStatusOptions
                });
            }
            this.model.fetchedAttributes = this.model.getClonedAttributes();

            this.additionalParams = this.getMetadata().get('app.additionalEntityParams.fields') || {};

            for (let param in this.additionalParams) {
                this.model.set(param, this.getMetadata().get(['scopes', this.scope, param]));
                let viewName = this.additionalParams[param].view || this.getFieldManager().getViewName(this.additionalParams[param].type);
                this.createView(param, viewName, {
                    model: this.model,
                    mode: 'edit',
                    el: `${this.options.el} .field[data-name="${param}"]`,
                    defs: {
                        name: param
                    },
                    tooltip: this.additionalParams[param].tooltip,
                    tooltipText: this.translate(param, 'tooltips', 'EntityManager'),
                    tooltipLink: this.translate(param, 'tooltipLink', 'EntityManager')
                });
            }

            /**
             * Create sortBy field
             */
            if (this.scope) {
                // prepare Field List
                var fieldDefs = this.getMetadata().get('entityDefs.' + this.scope + '.fields') || {};
                var orderableFieldList = Object.keys(fieldDefs).filter(function (item) {
                    if (fieldDefs[item].notStorable || fieldDefs[item].type == 'linkMultiple') {
                        return false;
                    }
                    return true;
                }, this).sort(function (v1, v2) {
                    return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
                }.bind(this));

                var translatedOptions = {};
                orderableFieldList.forEach(function (item) {
                    translatedOptions[item] = this.translate(item, 'fields', this.scope);
                }, this);

                this.createView('sortBy', 'views/fields/enum', {
                    model: this.model,
                    mode: 'edit',
                    el: this.options.el + ' .field[data-name="sortBy"]',
                    defs: {
                        name: 'sortBy',
                        params: {
                            options: orderableFieldList
                        }
                    },
                    translatedOptions: translatedOptions
                });
            }
        },

        hideField: function (name) {
            var view = this.getView(name);
            if (view) {
                view.disabled = true;
            }
            this.$el.find('.cell[data-name=' + name + ']').addClass('hidden');
        },

        isFieldHidden: function (name) {
            return this.$el.find('.cell[data-name=' + name + ']').hasClass('hidden');
        },

        showField: function (name) {
            var view = this.getView(name);
            if (view) {
                view.disabled = false;
            }
            this.$el.find('.cell[data-name=' + name + ']').removeClass('hidden');
        },

        afterRender: function () {
            this.getView('name').on('change', function (m) {
                var name = this.model.get('name');

                name = name.charAt(0).toUpperCase() + name.slice(1);

                this.model.set('labelSingular', name);
                this.model.set('labelPlural', name + 's');
                if (name) {
                    name = name.replace(/\-/g, ' ').replace(/_/g, ' ').replace(/[^\w\s]/gi, '').replace(/ (.)/g, function (match, g) {
                        return g.toUpperCase();
                    }).replace(' ', '');
                    if (name.length) {
                        name = name.charAt(0).toUpperCase() + name.slice(1);
                    }
                }
                this.model.set('name', name);
            }, this);

            if (!this.isNew) {
                this.manageKanbanFields({});
                this.listenTo(this.model, 'change:statusField', function (m, value, o) {
                    this.manageKanbanFields(o);
                }, this);

                this.manageKanbanViewModeField();
                this.listenTo(this.model, 'change:kanbanViewMode', function () {
                    this.manageKanbanViewModeField();
                }, this);
            }

            if (this.isNew) {
                this.hideField('disabled');
            }

            this.getView('type').on('change', () => this.managePanelsViewMode());

            this.managePanelsViewMode();
        },

        manageKanbanFields: function (o) {
            if (o.ui) {
                this.model.set('kanbanStatusIgnoreList', []);
            }
            if (this.model.get('statusField')) {
                this.setKanbanStatusIgnoreListOptions();
                this.showField('kanbanViewMode');
                if (this.model.get('kanbanViewMode')) {
                    this.showField('kanbanStatusIgnoreList');
                } else {
                    this.hideField('kanbanStatusIgnoreList');
                }
            } else {
                this.hideField('kanbanViewMode');
                this.hideField('kanbanStatusIgnoreList');
            }
        },

        manageKanbanViewModeField: function () {
            if (this.model.get('kanbanViewMode')) {
                this.showField('kanbanStatusIgnoreList');
            } else {
                this.hideField('kanbanStatusIgnoreList');
            }
        },

        setKanbanStatusIgnoreListOptions: function () {
            var statusField = this.model.get('statusField');
            var fieldView = this.getView('kanbanStatusIgnoreList');

            var optionList = this.getMetadata().get(['entityDefs', this.scope, 'fields', statusField, 'options']) || [];
            var translation = this.getMetadata().get(['entityDefs', this.scope, 'fields', statusField, 'translation']) || this.scope + '.options.' + statusField;

            fieldView.params.options = optionList;
            fieldView.params.translation = translation;

            fieldView.setupTranslation();

            fieldView.setOptionList(optionList);
        },

        managePanelsViewMode: function () {
            let additionalEntityParams = this.getMetadata().get('app.additionalEntityParams.layout') || [];

            Object.keys(additionalEntityParams).forEach((key) => {
                if ((additionalEntityParams[key].types || []).includes(this.model.get('type'))) {
                    this.$el.find('.panel.entity-manager-' + key).removeClass('hidden');
                } else {
                    this.$el.find('.panel.entity-manager-' + key).addClass('hidden');
                }
            });
        },

        actionSave: function () {
            let arr = [
                'name',
                'type',
                'labelSingular',
                'labelPlural',
                'disabled',
                'streamDisabled',
                'statusField',
                'iconClass'
            ];

            if (this.scope) {
                arr.push('sortBy');
                arr.push('sortDirection');
                arr.push('kanbanViewMode');
                arr.push('kanbanStatusIgnoreList');
            }

            if (this.hasColorField) {
                arr.push('color');
            }

            for (let param in this.additionalParams) {
                arr.push(param);
            }

            var notValid = false;

            arr.forEach(function (item) {
                if (!this.hasView(item)) return;
                if (this.getView(item).mode != 'edit') return;
                this.getView(item).fetchToModel();
            }, this);

            arr.forEach(function (item) {
                if (!this.hasView(item)) return;
                if (this.getView(item).mode != 'edit') return;
                notValid = this.getView(item).validate() || notValid;
            }, this);

            if (notValid) {
                return;
            }

            this.disableButton('save');
            this.disableButton('resetToDefault');

            var url = 'EntityManager/action/createEntity';
            if (this.scope) {
                url = 'EntityManager/action/updateEntity';
            }

            var name = this.model.get('name');

            var tmpData = {
                name: name,
                labelSingular: this.model.get('labelSingular'),
                labelPlural: this.model.get('labelPlural'),
                type: this.model.get('type'),
                disabled: this.model.get('disabled'),
                streamDisabled: this.model.get('streamDisabled'),
                textFilterFields: this.model.get('textFilterFields'),
                statusField: this.model.get('statusField'),
                iconClass: this.model.get('iconClass'),
            };

            if (this.hasColorField) {
                tmpData.color = this.model.get('color') || null
            }

            if (tmpData.statusField === '') {
                tmpData.statusField = null;
            }

            if (this.scope) {
                tmpData.sortBy = this.model.get('sortBy');
                tmpData.sortDirection = this.model.get('sortDirection');
                tmpData.kanbanViewMode = this.model.get('kanbanViewMode');
                tmpData.kanbanStatusIgnoreList = this.model.get('kanbanStatusIgnoreList');
            }

            for (let param in this.additionalParams) {
                tmpData[param] = this.model.get(param);
            }

            let data = {};
            $.each(tmpData, (name, val) => {
                if (!this.isFieldHidden(name)) {
                    data[name] = val;
                }
            });

            $.ajax({
                url: url,
                type: 'POST',
                data: JSON.stringify(data),
                error: function () {
                    this.enableButton('save');
                    this.enableButton('resetToDefault');
                }.bind(this)
            }).done(function () {
                if (this.scope) {
                    Espo.Ui.success(this.translate('Saved'));
                } else {
                    Espo.Ui.success(this.translate('entityCreated', 'messages', 'EntityManager'));
                }
                var global = ((this.getLanguage().data || {}) || {}).Global;
                (global.scopeNames || {})[name] = this.model.get('labelSingular');
                (global.scopeNamesPlural || {})[name] = this.model.get('labelPlural');

                Promise.all([
                    new Promise(function (resolve) {
                        this.getMetadata().load(function () {
                            resolve();
                        }, true);
                    }.bind(this)),
                    new Promise(function (resolve) {
                        this.getConfig().load(function () {
                            resolve();
                        }, true);
                    }.bind(this)),
                    new Promise(function (resolve) {
                        this.getLanguage().load(function () {
                            resolve();
                        }, true);
                    }.bind(this))
                ]).then(function () {
                    this.trigger('after:save');
                }.bind(this));

            }.bind(this));
        },

        actionResetToDefault: function () {
            this.confirm(this.translate('confirmation', 'messages'), function () {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                this.ajaxPostRequest('EntityManager/action/resetToDefault', {
                    scope: this.scope
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
                        this.setupData();
                        this.model.fetchedAttributes = this.model.getClonedAttributes();
                        this.notify('Done', 'success');
                    }.bind(this));
                }.bind(this));
            }, this);
        }

    });
});
