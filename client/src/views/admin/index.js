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

        data: function () {
            return {
                panelDataList: this.panelDataList,
                iframeUrl: this.iframeUrl,
                iframeHeight: this.getConfig().get('adminPanelIframeHeight') || 1023
            };
        },

        setup: function () {
            this.panelDataList = [];

            var panels = this.getMetadata().get('app.adminPanel') || {};
            for (var name in panels) {
                var panelItem = Espo.Utils.cloneDeep(panels[name]);
                panelItem.name = name;
                panelItem.itemList = panelItem.itemList || [];

                panelItem.itemList.forEach(item => {
                    if (item.url === '#Admin/rebuildDb') {
                        item.hasWarning = (localStorage.getItem('pd_isNeedToRebuildDatabase') || false) === 'true';
                        item.warningText = this.getLanguage().translate('rebuildDbWarning', 'labels', 'Admin');
                    } else if (item.url === '#Composer/list') {
                        item.hasWarning = (localStorage.getItem('pd_isNeedToUpdate') || false) === 'true';
                        item.warningText = this.getLanguage().translate('updatesAvailable', 'labels', 'Admin');
                    } else if (item.url === '#Admin/clearCache' && Espo?.loader?.cacheTimestamp) {
                        const date = moment.unix(Espo.loader.cacheTimestamp).tz(this.getDateTime().getTimeZone()).format(this.getDateTime().getDateTimeFormat());
                        item.tooltip = this.getLanguage().translate('clearCacheTooltip', 'labels', 'Admin') + ' ' + date;
                    }
                }, this);

                if (panelItem.items) {
                    panelItem.items.forEach(function (item) {
                        panelItem.itemList.push(item);
                    }, this);
                }
                this.panelDataList.push(panelItem);
            }

            this.panelDataList.sort(function (v1, v2) {
                if (!('order' in v1) && ('order' in v2)) return 0;
                if (!('order' in v2)) return 0;
                return v1.order - v2.order;
            }.bind(this));

            var iframeParams = [
                'version=' + encodeURIComponent(''),
                'css=' + encodeURIComponent(this.getConfig().get('siteUrl') + '/' + this.getThemeManager().getStylesheet())
            ];
            this.iframeUrl = this.getConfig().get('adminPanelIframeUrl') || 'https://s.espocrm.com/';
            if (~this.iframeUrl.indexOf('?')) {
                this.iframeUrl += '&' + iframeParams.join('&');
            } else {
                this.iframeUrl += '?' + iframeParams.join('&');
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

            new Svelte.BaseHeader({
                target: $(`${this.options.el} .page-header`).get(0),
                props: {
                    breadcrumbs: [
                        {
                            label: this.getLanguage().translate('Administration'),
                            url: '#Admin'
                        }
                    ],
                    scope: 'App',
                    id: 'Administration'
                }
            });

            new Svelte.TreePanel({
                target: $(`${this.options.el} .content-wrapper`).get(0),
                anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                props: {
                    scope: this.scope,
                    model: this.model,
                    mode: 'detail',
                    isAdminPage: true,
                    callbacks: {

                    }
                }
            });
        }

    });
});
