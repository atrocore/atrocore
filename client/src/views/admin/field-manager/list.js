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

Espo.define('views/admin/field-manager/list', 'view', function (Dep) {

    return Dep.extend({

        template: 'admin/field-manager/list',

        data: function () {
            // get scope fields
            let scopeFields = this.getMetadata().get('entityDefs.' + this.scope + '.fields');

            // prepare fieldDefsArray
            let fieldDefsArray = [];
            $.each(this.fieldDefsArray, function (k, v) {
                v.label = this.getLanguage().translate(v.name, 'fields', this.scope);
                if (scopeFields[v.name].labelField) {
                    v.label = this.getLanguage().translate(scopeFields[v.name].labelField, 'fields', this.scope);
                }
                v.emDisabled = scopeFields[v.name].emDisabled ?? false;
                if (!scopeFields[v.name].emHidden) {
                    fieldDefsArray.push(v);
                }
            }.bind(this));

            if (this.scope === 'Asset') {
                this.typeList = this.typeList.filter(function (type) {
                    return type !== 'asset';
                });
            }

            return {
                scope: this.scope,
                fieldDefsArray: fieldDefsArray,
                typeList: this.typeList
            };
        },

        events: {
            'click [data-action="removeField"]': function (e) {
                var field = $(e.currentTarget).data('name');

                this.confirm(this.translate('confirmation', 'messages'), function () {
                    this.notify('removing');
                    $.ajax({
                        url: 'Admin/fieldManager/' + this.scope + '/' + field,
                        type: 'DELETE',
                        success: function () {
                            this.notify('Removed', 'success');
                            this.clearFilters(field)
                            var data = this.getMetadata().data;
                            delete data['entityDefs'][this.scope]['fields'][field];
                            this.getMetadata().storeToCache();
                            location.reload();
                        }.bind(this),
                    });
                }, this);
            }
        },

        setup: function () {
            this.scope = this.options.scope;

            this.typeList = [];

            var fieldDefs = this.getMetadata().get('fields');

            Object.keys(this.getMetadata().get('fields')).forEach(function (type) {
                if (type in fieldDefs) {
                    if (!fieldDefs[type].notCreatable) {
                        this.typeList.push(type);
                    }
                }
            }, this);

            this.typeList.sort(function (v1, v2) {
                return this.translate(v1, 'fieldTypes', 'Admin').localeCompare(this.translate(v2, 'fieldTypes', 'Admin'));
            }.bind(this));

            this.wait(true);
            this.getModelFactory().create(this.scope, function (model) {

                this.fields = model.defs.fields;
                this.fieldList = Object.keys(this.fields).sort();
                this.fieldDefsArray = [];
                this.fieldList.forEach(function (field) {
                    var defs = this.fields[field];
                    if (defs.customizationDisabled) return;
                    this.fieldDefsArray.push({
                        name: field,
                        isCustom: defs.isCustom || false,
                        type: defs.type
                    });
                }, this);


                this.wait(false);
            }.bind(this));

        },

        clearFilters(field) {
            let presetFilters = this.getPreferences().get('presetFilters') || {};
            if (!(this.scope in presetFilters)) {
                presetFilters[this.scope] = [];
            }

            presetFilters[this.scope].forEach(function (item, index, obj) {
                for (let filterField in item.data) {
                    let name = filterField.split('-')[0];

                    if (name === field) {
                        delete obj[index].data[filterField]
                    }
                }
            }, this);
            presetFilters[this.scope] = presetFilters[this.scope].filter(item => Object.keys(item.data).length > 0);

            this.getPreferences().set('presetFilters', presetFilters);
            this.getPreferences().save({patch: true});
            this.getPreferences().trigger('update');

            let filters = this.getStorage().get('listSearch', this.scope);
            if (filters && filters.advanced) {
                for (let filter in filters.advanced) {
                    let name = filter.split('-')[0];

                    if (name === field) {
                        delete filters.advanced[filter]
                    }
                }

                if (filters.presetName && !presetFilters[this.scope].includes(filters.presetName)) {
                    filters.presetName = null
                }

                this.getStorage().set('listSearch', this.scope, filters);
            }
        }
    });

});
