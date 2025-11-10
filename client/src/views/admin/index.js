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

Espo.define('views/admin/index', ['view', 'lib!JsTree'], function (Dep) {

    return Dep.extend({

        template: 'admin/index',

        cacheDate: null,

        data: function () {
            return {
                panelDataList: this.panelDataList,
                iframeUrl: this.iframeUrl,
                iframeHeight: this.getConfig().get('adminPanelIframeHeight') || 1023
            };
        },

        setup: function () {
            if (Espo?.loader?.cacheTimestamp) {
                this.cacheDate = moment.unix(Espo.loader.cacheTimestamp).tz(this.getDateTime().getTimeZone()).format(this.getDateTime().getDateTimeFormat());
            }

            this.once('after:render', function () {
                this.logToNavigationHistory('Administration');
            });
        },

        updatePageTitle: function () {
            this.setPageTitle(this.getLanguage().translate('Administration'));
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            new Svelte.Administration({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                props: {
                    cacheDate: this.cacheDate
                }
            });
        }

    });
});
