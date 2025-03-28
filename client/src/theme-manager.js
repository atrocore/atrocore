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

Espo.define('theme-manager', [], function () {

    var ThemeManager = function (config, preferences, metadata) {
        this.config = config;
        this.preferences = preferences;
        this.metadata = metadata;
    };

    _.extend(ThemeManager.prototype, {

        defaultParams: {
            screenWidthXs: 768,
            dashboardCellHeight: 155,
            dashboardCellMargin: 19
        },

        getName: function () {
            return 'TreoClassicTheme';
        },

        getAppliedName: function () {
            return window.getComputedStyle(document.body).getPropertyValue('--theme-name');
        },

        isApplied: function () {
            var appliedName = this.getAppliedName();
            if (!appliedName) return true;
            return this.getName() === appliedName;
        },

        getStylesheet: function () {
            var link = 'client/modules/treo-core/css/treo/treo-classic-theme.css';
            if (this.config.get('cacheTimestamp')) {
                link += '?r=' + this.config.get('cacheTimestamp').toString();
            }
            return link
        },

        getCustomStylesheet: function () {
            let style = this.getStyle();

            if (style && style['customStylesheetPath']) {
                let link = style['customStylesheetPath'];

                if (this.config.get('cacheTimestamp')) {
                    link += '?r=' + this.config.get('cacheTimestamp').toString();
                }
                return link;
            }
            return null;
        },

        getIframeStylesheet: function () {
            var link = this.metadata.get(['themes', this.getName(), 'stylesheetIframe']) || 'client/css/espo/espo-iframe.css';
            if (this.config.get('cacheTimestamp')) {
                link += '?r=' + this.config.get('cacheTimestamp').toString();
            }
            return link
        },

        getParam: function (name) {
            let style = this.getStyle() ||  {};
            return style[name] ||  this.metadata.get(['themes', this.getName(), name]) || this.defaultParams[name] || null;
        },

        isUserTheme: function () {
            if (!this.config.get('userThemesDisabled')) {
                var name = this.preferences.get('styleId');
                if (name && name !== '') {
                    if (name !== this.config.get('defaultStyleId')) {
                        return true;
                    }
                }
            }
            return false;
        },

        getStyle() {
            let defaultStyleId = this.preferences.get('styleId') ||  this.config.get('defaultStyleId');

            if(!defaultStyleId) {
                return null;
            }

            let styles = (this.config.get('referenceData') ||  {})['Style'] ||  {};

            for (const key in styles) {
                if(styles[key].id === defaultStyleId) {
                    return styles[key];
                }
            }
        },
    });

    return ThemeManager;
});
