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

Espo.define('views/measure/fields/locale-units', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        localeId: null,

        setup() {
            this.params.options = [];
            this.options.translatedOptions = {};

            $.each(this.model.get('unitsNames'), (id, name) => {
                this.params.options.push(id);
                this.options.translatedOptions[id] = name;
            });

            this.prepareLocaleId();

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:localeUnits', () => {
                let data = this.model.get('data') || {};
                data[`locale_${this.localeId}`] = this.model.get('localeUnits');
                this.model.set('data', data);
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

            if (this.localeId) {
                this.model.set('localeId', this.localeId);
                this.show();
            }
        },

        prepareLocaleId() {
            const hash = window.location.hash;
            if (hash.indexOf("#Locale/view/") >= 0) {
                this.localeId = hash.replace("#Locale/view/", "");
            }
            if (!this.localeId && hash.indexOf("#Locale/edit/") >= 0) {
                this.localeId = hash.replace("#Locale/edit/", "");
            }
        },

    });
});


