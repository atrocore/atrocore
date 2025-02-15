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

Espo.define('views/modals/mass-update', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'mass-update',

        header: false,

        template: 'modals/mass-update',

        fullHeight: true,

        data: function () {
            return {
                scope: this.scope,
                fields: this.fields
            };
        },

        events: {
            'click button[data-action="update"]': function () {
                this.update();
            },
            'click a[data-action="add-field"]': function (e) {
                var field = $(e.currentTarget).data('name');
                this.addField(field);
            },
            'click button[data-action="reset"]': function (e) {
                this.reset();
            }
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'update',
                    label: 'Update',
                    style: 'danger',
                    disabled: true
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.scope = this.options.scope;
            this.ids = this.options.ids;
            this.where = this.getWhere();
            this.selectData = this.options.selectData;
            this.byWhere = this.options.byWhere;

            this.header = this.translate(this.scope, 'scopeNamesPlural') + ' &raquo ' + this.translate('Mass Update');

            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;
                this.model.set({ids: this.ids});
                let forbiddenFieldList = this.getAcl().getScopeForbiddenFieldList(this.scope) || [];

                this.fields = [];
                $.each((this.getMetadata().get(`entityDefs.${this.scope}.fields`) || {}), (field, row) => {
                    if (~forbiddenFieldList.indexOf(field)) return;
                    if (row.layoutMassUpdateDisabled) return;
                    if (row.massUpdateDisabled) return;
                    this.fields.push(field);
                });
                this.fields.sort()
            }.bind(this));

            this.fieldList = [];
        },

        addField: function (name) {
            this.enableButton('update');

            this.$el.find('[data-action="reset"]').removeClass('hidden');

            this.$el.find('ul.filter-list li[data-name="'+name+'"]').addClass('hidden');

            if (this.$el.find('ul.filter-list li:not(.hidden)').size() == 0) {
                this.$el.find('button.select-field').addClass('disabled').attr('disabled', 'disabled');
            }

            this.notify('Loading...');
            var label = this.translate(name, 'fields', this.scope);
            var html = '<div class="cell form-group col-sm-6" data-name="'+name+'"><label class="control-label">'+label+'</label><div class="field" data-name="'+name+'" /></div>';
            this.$el.find('.fields-container').append(html);

            var type = this.model.getFieldType(name);

            var viewName = this.model.getFieldParam(name, 'view') || this.getFieldManager().getViewName(type);

            this.createView(name, viewName, {
                model: this.model,
                el: this.getSelector() + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    isMassUpdate: true
                },
                mode: 'edit'
            }, function (view) {
                this.fieldList.push(name);
                view.render();
                view.notify(false);
            }.bind(this));
        },

        actionUpdate: function () {
            this.disableButton('update');

            var self = this;

            var attributes = this.prepareData();

            this.model.set(attributes);

            if (!this.isValid()) {
                self.notify('Saving...');
                $.ajax({
                    url: this.scope + '/action/massUpdate',
                    type: 'PUT',
                    data: JSON.stringify({
                        attributes: attributes,
                        ids: self.ids || null,
                        where: (!self.ids || self.ids.length == 0) ? self.options.where : null,
                        selectData: (!self.ids || self.ids.length == 0) ? self.options.selectData : null,
                        byWhere: this.byWhere
                    }),
                    success: function (result) {
                        self.trigger('after:update', result);
                    },
                    error: function () {
                        self.notify('Error occurred', 'error');
                        self.enableButton('update');
                    }
                });
            } else {
                this.notify('Not valid', 'error');
                this.enableButton('update');
            }
        },

        reset: function () {
            this.fieldList.forEach(function (field) {
                this.clearView(field);
                this.$el.find('.cell[data-name="'+field+'"]').remove();
            }, this);

            this.fieldList = [];

            this.model.clear();

            this.$el.find('[data-action="reset"]').addClass('hidden');

            this.$el.find('button.select-field').removeClass('disabled').removeAttr('disabled');
            this.$el.find('ul.filter-list').find('li').removeClass('hidden');

            this.disableButton('update');
        },

        prepareData() {
            var attributes = {};
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                _.extend(attributes, view.fetch());
            }.bind(this));

            return attributes;
        },

        isValid() {
            var notValid = false;
            this.fieldList.forEach(function (field) {
                var view = this.getView(field);
                notValid = view.validate() || notValid;
            }.bind(this));

            return notValid;
        },

        getWhere(){
            let where = this.options.where;
            let cleanWhere = (where) => {
                where.forEach(wherePart => {
                    if(['in', 'notIn'].includes(wherePart['type'])) {
                        if ('value' in wherePart && !(wherePart['value'] ?? []).length){
                            delete wherePart['value']
                        }
                    }

                    if(['and', 'or'].includes(wherePart['type']) && Array.isArray(wherePart['value'] ?? [])){
                        cleanWhere(wherePart['value'] ?? [])
                    }
                })
            };
            cleanWhere(where);
            return where;
        }
    });
});
