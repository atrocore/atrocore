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

Espo.define('views/admin/field-manager/fields/foreign-name', 'views/fields/enum',
    Dep => {

        return Dep.extend({

            prohibitedEmptyValue: true,

            afterRender() {
                Dep.prototype.afterRender.call(this);

                if (!this.model.get('foreignName')) {
                    this.model.set('foreignName', 'name');
                }
            },

            setupOptions: function () {
                const foreign = this.getMetadata().get(`entityDefs.${this.model.scope}.links.${this.model.get('name')}.entity`);
                const foreignFields = this.getMetadata().get(`entityDefs.${foreign}.fields`) || {};

                this.params.options = [];
                this.translatedOptions = {};
                $.each(foreignFields, (name, data) => {
                    if (data.type === 'varchar') {
                        this.params.options.push(name);
                        this.translatedOptions[name] = this.translate(name, 'fields', foreign);
                    } else if (data.type === 'link' && ['ownerUser', 'assignedUser'].includes(name)) {
                        let linkEntity = this.getMetadata().get(['entityDefs', foreign, 'links', name, 'entity']);
                        if (linkEntity && this.getMetadata().get(['entityDefs', linkEntity, 'fields', 'name'])) {
                            this.params.options.push(name + 'Name');
                            this.translatedOptions[name + 'Name'] = this.translate('name', 'fields', 'Global') + ': ' + this.translate(name, 'fields', foreign);
                        }
                    }
                });
            },

        });

    });
