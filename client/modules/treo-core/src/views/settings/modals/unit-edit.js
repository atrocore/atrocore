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

Espo.define('treo-core:views/settings/modals/unit-edit', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:settings/modals/unit-edit',

        configuration: {},

        buttonList: [
            {
                name: 'save',
                label: 'Save',
                style: 'primary',
            },
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup() {
            Dep.prototype.setup.call(this);

            this.id = this.options.id;
            this.configuration = this.options.configuration;

            this.setupHeader();

            if (this.model) {
                this.getModelFactory().create(null, model => {
                    model = this.model.clone();
                    model.id = this.model.id;
                    model.defs = this.model.defs;
                    this.model = model;
                });
            }

            this.setupOptionFields();
        },

        setupOptionFields() {
            this.createView('measure', 'views/fields/varchar', {
                el: `${this.options.el} .field[data-name="measure"]`,
                model: this.model,
                name: 'measure',
                mode: 'edit',
                params: {
                    trim: true,
                    required: true,
                    readOnly: !!this.id
                }
            }, view => view.render());

            this.createView('units', 'views/fields/array', {
                el: `${this.options.el} .field[data-name="units"]`,
                model: this.model,
                name: 'units',
                mode: 'edit',
                params: {
                    noEmptyString: true,
                    required: true
                }
            }, view => view.render());
        },

        setupHeader() {
            let measure = this.getLanguage().translate('measure', 'fields', 'Global');
            if (!this.id) {
                this.header = `${this.getLanguage().translate('Create', 'labels', 'Global')} ${measure}`;
            } else {
                this.header = `${this.getLanguage().translate('Edit')}: ${measure}`;
            }
        },

        actionSave() {
            if (this.validate()) {
                this.notify('Not valid', 'error');
                return;
            }
            this.trigger('after:save', this.model);
            this.close();
        },

        validate() {
            let notValid = false;
            let fields = this.nestedViews || {};
            for (let i in fields) {
                if (fields[i].mode === 'edit') {
                    if (!fields[i].disabled && !fields[i].readOnly) {
                        notValid = fields[i].validate() || notValid;
                    }
                }
            }
            return notValid
        },


    })
);