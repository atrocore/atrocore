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

Espo.define('views/stream/notes/update', 'views/stream/note', function (Dep) {

    return Dep.extend({

        template: 'stream/notes/update',

        messageName: 'updateFromTo',

        customLabels: {},

        data: function () {
            const diff = this.model.get('diff');
            const showInline = this.model.get('data').fields.length === 1 && !diff;

            let detailFieldsArr = [],
                diffArr = [];

            this.fieldsArr.forEach(function (item) {
                if (diff && typeof diff === 'object' && Object.keys(diff).includes(item.field)) {
                    if (diff[item.field] !== null && diff[item.field] !== '') {
                        return;
                    }
                }

                detailFieldsArr.push(item);
            });

            Object.keys(diff || {}).forEach(function (field) {
                diffArr.push({
                    field: this.translate(field, 'fields', this.model.get('parentType')),
                    diff: diff[field]
                })
            }, this);

            return _.extend({
                fieldsArr: this.fieldsArr,
                detailFieldsArr: detailFieldsArr,
                changedFieldsStr: (this.fieldsArr.map(item => '<code>' + item.label + '</code>')).join(', '),
                parentType: this.model.get('parentType'),
                diffArr: diffArr,
                showDiff: typeof diff !== 'undefined',
                showInline: showInline,
                showCommon: !showInline
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click a[data-action="expandDetails"]': function (e) {
                if (this.$el.find('.details').hasClass('hidden')) {
                    this.$el.find('.details').removeClass('hidden');
                    $(e.currentTarget).find('span').removeClass('fa-angle-down').addClass('fa-angle-up');
                } else {
                    this.$el.find('.details').addClass('hidden');
                    $(e.currentTarget).find('span').addClass('fa-angle-down').removeClass('fa-angle-up');
                }
            }
        },

        init: function () {
            if (this.getUser().isAdmin()) {
                this.isRemovable = true;
            }
            if (this.model.get('data').fields.length === 1) {
                if (this.model.get('diff')) {
                    this.messageName = "updateOne";
                }
            }

            if (this.model.get('data').entityType) {
                this.messageName += 'In'
            }

            Dep.prototype.init.call(this);

        },

        setup: function () {
            var data = this.model.get('data');
            let parentType = data.entityType ?? this.model.get('parentType')
            var fields = data.fields || [];

            if (data.entityType) {
                const entityType = this.model.get('relatedType') || data.relatedType || null;
                const entityId = this.model.get('relatedId') || data.relatedId || null;
                const entityName = this.model.get('relatedName') || data.relatedName || null

                this.messageData['relatedEntityType'] = this.translateEntityType(entityType);
                this.messageData['relatedEntity'] = '<a href="#' + entityType + '/view/' + entityId + '">' + Handlebars.Utils.escapeExpression(entityName) + '</a>';
            }
            this.createMessage();

            this.wait(true);
            this.getModelFactory().create(parentType, function (model) {
                var modelWas = model;
                var modelBecame = model.clone();

                data.attributes = data.attributes || {};

                modelWas.set(data.attributes.was);
                modelBecame.set(data.attributes.became);

                this.fieldsArr = [];

                fields.forEach(function (field) {
                    let fieldDefs = this.model.get('fieldDefs') || this.getMetadata().get(['entityDefs', parentType, 'fields']) || {};
                    if (!fieldDefs[field] || !fieldDefs[field]['type']) {
                        return;
                    }

                    let type = fieldDefs[field]['type'];

                    let fieldId = field;
                    if (type === 'file' || type === 'link') {
                        fieldId = field + 'Id';
                    } else if (type === 'linkMultiple') {
                        fieldId = field + 'Ids';
                    }

                    if (model.getFieldParam(field, 'isMultilang') && !modelWas.has(fieldId) && !modelBecame.has(fieldId)) {
                        return;
                    }

                    // skip if empty on both sides
                    if ((modelWas.get(fieldId) === null || modelWas.get(fieldId) === '') && (modelBecame.get(fieldId) === null || modelBecame.get(fieldId) === '') && modelWas.get(fieldId) === modelBecame.get(fieldId)) {
                        return;
                    }

                    let params = {};
                    if (fieldDefs[field].extensibleEnumId) {
                        params.extensibleEnumId = fieldDefs[field].extensibleEnumId
                    }
                    if (('typeValue' in data) && ('typeValueIds' in data)) {
                        params.options = data.typeValueIds[field];
                        params.translatedOptions = {};
                        params.options.forEach((option, k) => {
                            params.translatedOptions[option] = data.typeValue[field][k];
                        });
                    }

                    let viewName = model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);
                    this.createView(field + 'Was', viewName, {
                        el: this.options.el + ' .was',
                        model: modelWas,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                        params: params
                    }, function (view) {
                        view.render();

                        this.listenTo(view, 'after:render', () => {
                            if (modelWas.get(field) === '' || (Array.isArray(modelWas.get(field)) && modelWas.get(field).length === 0)) {
                                view.$el.html('<span class="text-gray">&#9141;</span>');
                            }
                        });
                    });

                    this.createView(field + 'Became', viewName, {
                        el: this.options.el + ' .became',
                        model: modelBecame,
                        readOnly: true,
                        defs: {
                            name: field
                        },
                        mode: 'detail',
                        inlineEditDisabled: true,
                        params: params
                    }, function (view) {
                        view.render();

                        this.listenTo(view, 'after:render', () => {
                            if (modelBecame.get(field) === '' || (Array.isArray(modelBecame.get(field)) && modelBecame.get(field).length === 0)) {
                                view.$el.html('<span class="text-gray">&#9141;</span>');
                            }
                        });
                    });

                    let htmlTag = 'code';

                    if (['bool', 'color', 'enum', 'multiEnum', 'extensibleEnum', 'extensibleMultiEnum'].includes(type)) {
                        htmlTag = 'span';
                    }

                    this.fieldsArr.push({
                        field: field,
                        label: fieldDefs[field]['label'] ?? field,
                        was: field + 'Was',
                        htmlTag: htmlTag,
                        became: field + 'Became',
                        customLabel: this.customLabels[field] ? this.customLabels[field] : false
                    });

                }, this);

                this.wait(false);

            }, this);
        },

    });
});

