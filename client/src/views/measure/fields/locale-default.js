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

Espo.define('views/measure/fields/locale-default', 'views/fields/enum',
    Dep => {
        return Dep.extend({

            setup() {
                this.prepareUnitsOptions();

                Dep.prototype.setup.call(this);

                this.listenTo(this.model, 'change:localeUnits', () => {
                    this.prepareUnitsOptions();
                    this.translatedOptions = Espo.Utils.cloneDeep(this.options.translatedOptions);
                    this.reRender();
                });

                this.listenTo(this.model, 'change:localeUnits', () => {
                    if (this.mode === 'edit') {
                        const selected = $("select[name=\"localeDefault\"] option:selected").attr('value');
                        if (selected && selected !== this.model.get('localeDefault')) {
                            this.model.set('localeDefault', selected);
                        }
                    }
                });

                this.listenTo(this.model, 'change:localeDefault', () => {
                    if (this.mode === 'edit') {
                        let data = this.model.get('data') || {};
                        data[`locale_${this.model.get('localeId')}_default`] = this.model.get('localeDefault');
                        this.model.set('data', data);
                    }
                });
            },

            prepareUnitsOptions() {
                this.params.options = [];
                this.options.translatedOptions = {};

                $.each(this.model.get('unitsNames'), (id, name) => {
                    if ((this.model.get('localeUnits') || []).includes(id)) {
                        this.params.options.push(id);
                        this.options.translatedOptions[id] = name;
                    }
                });
            },

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (this.mode !== 'list') {
                    this.checkFieldVisibility();
                }
            },

            checkFieldVisibility() {
                this.hide();

                if (this.hasLocale()) {
                    this.show();
                }
            },

            isRequired: function () {
                if (this.mode !== 'list') {
                    return this.hasLocale();
                }

                return false;
            },

            hasLocale() {
                return !!(this.getParentView().getView('localeUnits').localeId);
            },

        });
    });


