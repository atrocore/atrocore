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
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('treo-core:views/admin/entity-manager/modals/edit-entity', 'class-replace!treo-core:views/admin/entity-manager/modals/edit-entity', function (Dep) {

    return Dep.extend({

        template: 'treo-core:admin/entity-manager/modals/edit-entity',

        data() {
            return _.extend({
                additionalParamsLayout: this.additionalParamsLayout
            }, Dep.prototype.data.call(this));
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.additionalParamsLayout = [];
            this.additionalParams = this.getMetadata().get(['app', 'additionalEntityParams']) || {};

            // set scope specified additional params
            const additionalScopeParams = this.getMetadata().get(['app', 'additional' + this.scope + 'Params']);
            if (additionalScopeParams) {
                $.each(additionalScopeParams, function (field, data) {
                    this.additionalParams[field] = data;
                }.bind(this));
            }

            for (let param in this.additionalParams) {
                this.model.set(param, this.getMetadata().get(['scopes', this.scope, param]) || this.additionalParams[param].default);

                let tooltipText = this.additionalParams[param].tooltip ? this.translate(param, 'tooltips', 'EntityManager') : null;

                let viewName = this.additionalParams[param].view || this.getFieldManager().getViewName(this.additionalParams[param].type);
                this.createView(param, viewName, {
                    model: this.model,
                    mode: 'edit',
                    el: `${this.options.el} .field[data-name="${param}"]`,
                    defs: {
                        name: param
                    },
                    tooltip: this.additionalParams[param].tooltip && tooltipText,
                    tooltipText: tooltipText
                });

                if (!this.additionalParamsLayout.length || this.additionalParamsLayout[this.additionalParamsLayout.length - 1].length > 1) {
                    this.additionalParamsLayout.push([param]);
                } else {
                    this.additionalParamsLayout[this.additionalParamsLayout.length - 1].push(param);
                }
            }/**
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

        actionSave: function () {
            var arr = [
                'name',
                'type',
                'labelSingular',
                'labelPlural',
                'stream',
                'disabled',
                'statusField'
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
                if (!this.hasView(item))
                    return;
                if (this.getView(item).mode != 'edit')
                    return;
                this.getView(item).fetchToModel();
            }, this);

            arr.forEach(function (item) {
                if (!this.hasView(item))
                    return;
                if (this.getView(item).mode != 'edit')
                    return;
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

            var data = {
                name: name,
                labelSingular: this.model.get('labelSingular'),
                labelPlural: this.model.get('labelPlural'),
                type: this.model.get('type'),
                stream: this.model.get('stream'),
                disabled: this.model.get('disabled'),
                textFilterFields: this.model.get('textFilterFields'),
                fullTextSearch: this.model.get('fullTextSearch'),
                statusField: this.model.get('statusField'),
                iconClass: this.model.get('iconClass')
            };

            if (this.hasColorField) {
                data.color = this.model.get('color') || null
            }

            if (data.statusField === '') {
                data.statusField = null;
            }

            if (this.scope) {
                data.sortBy = this.model.get('sortBy');
                data.sortDirection = this.model.get('sortDirection');
                data.kanbanViewMode = this.model.get('kanbanViewMode');
                data.kanbanStatusIgnoreList = this.model.get('kanbanStatusIgnoreList');
            }

            for (let param in this.additionalParams) {
                data[param] = this.model.get(param);
            }

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

    });
});

