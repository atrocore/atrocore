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

Espo.define('treo-core:views/edit', 'class-replace!treo-core:views/edit',
    Dep => Dep.extend({
        getHeader: function () {
            const headerIconHtml = this.getHeaderIconHtml();
            const arr = [];
            let html = '';

            if (this.options.noHeaderLinks) {
                arr.push(this.getLanguage().translate(this.scope, 'scopeNamesPlural'));
            } else {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                arr.push(headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>');
            }

            if (this.model.isNew()) {
                arr.push(this.getLanguage().translate('New'));
            } else {
                let name = Handlebars.Utils.escapeExpression(this.model.get('name'));

                if (name === '') {
                    name = this.model.id;
                }

                if (this.options.noHeaderLinks) {
                    arr.push(name);
                } else {
                    arr.push('<a href="#' + this.scope + '/view/' + this.model.id + '" class="action">' + name + '</a>');
                }
            }
            return this.buildHeaderHtml(arr);
        },
    })
);