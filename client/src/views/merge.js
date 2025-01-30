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

Espo.define('views/merge', 'views/main', function (Dep) {

    return Dep.extend({

        template: 'merge',

        el: '#main',

        scope: null,

        name: 'Merge',

        headerView: 'views/header',

        recordView: 'views/record/merge',

        setup: function () {
            this.models = this.options.models;

            this.setupHeader();
            this.setupRecord();
        },

        setupHeader: function () {
            this.createView('header', this.headerView, {
                model: this.model,
                el: '#main > main > .page-header'
            });
        },

        setupRecord: function () {
            this.createView('body', this.recordView, {
                el: '#main > main > .body',
                models: this.models,
                collection: this.collection
            });
        },

        getHeader: function () {
            var html = '<a href="#' + this.models[0].name + '">' + this.getLanguage().translate(this.models[0].name, 'scopeNamesPlural') + '</a>';
            html += ' &raquo ';
            html += this.getLanguage().translate('merge');
            return html;
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Merge'));
        },
    });
});

