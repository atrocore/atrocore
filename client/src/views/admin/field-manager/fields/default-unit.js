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

Espo.define('views/admin/field-manager/fields/default-unit', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.prepareOptionsList();
            this.listenTo(this.model, 'change:measureId', () => {
                this.model.set('defaultUnit', null);
                this.prepareOptionsList();
                this.reRender();
            });
        },

        prepareOptionsList() {
            this.params.options = [''];
            this.translatedOptions = {'': ''};

            if (this.model.get('measureId')) {
                this.getMeasureUnits(this.model.get('measureId')).forEach(option => {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name ? option.name : ' ';
                });
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if(this.mode === 'list'){
                return;
            }

            this.$el.parent().hide();
            if (this.model.get('measureId')) {
                this.$el.parent().show();
            }
        },

    });

});
