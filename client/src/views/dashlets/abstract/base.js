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

Espo.define('views/dashlets/abstract/base', 'view', function (Dep) {

    return Dep.extend({

        optionsData: null,

        actionRefresh: function () {
            this.render();
        },

        actionOptions: function () {},

        prepareLayoutAfterConverting(layout) {
            return layout;
        },

        optionsFields: {
            "title": {
                "type": "varchar",
                "required": true
            },
            "autorefreshInterval": {
                "type": "enumFloat",
                "options": [0, 0.5, 1, 2, 5, 10]
            }
        },

        disabledForReadOnlyActionList: ['options', 'remove'],

        init: function () {
            this.name = this.options.name || this.name;
            this.id = this.options.id;

            this.defaultOptions = this.getMetadata().get(['dashlets', this.name, 'options', 'defaults']) || this.defaultOptions || {};

            this.defaultOptions = _.extend({
                title: this.getLanguage().translate(this.name, 'dashlets'),
            }, this.defaultOptions);

            this.defaultOptions = Espo.Utils.clone(this.defaultOptions);

            this.optionsFields = this.getMetadata().get(['dashlets', this.name, 'options', 'fields']) || this.optionsFields || {};
            this.optionsFields = Espo.Utils.clone(this.optionsFields);

            this.setupDefaultOptions();

            var options = Espo.Utils.cloneDeep(this.defaultOptions);

            for (var key in options) {
                if (typeof options[key] == 'function') {
                    options[key] = options[key].call(this);
                }
            }

            if (!this.options.readOnly) {
                var storedOptions = this.getPreferences().getDashletOptions(this.id) || {};
            } else {
                var storedOptions = (this.getConfig().get('dashletsOptions') || {})[this.id] || {};
            }

            this.optionsData = _.extend(options, storedOptions);

            if (this.optionsData.autorefreshInterval || false) {
                var interval = this.optionsData.autorefreshInterval * 60 * 1000;

                var t;
                var process = function () {
                    t = setTimeout(function () {
                        this.actionRefresh();
                        process();
                    }.bind(this), interval);
                }.bind(this);

                process();

                this.once('remove', function () {
                    clearTimeout(t);
                }, this);
            }

            this.actionList = Espo.Utils.clone(this.actionList);
            this.buttonList = Espo.Utils.clone(this.buttonList);

            if (this.options.readOnly) {
                this.actionList = this.actionList.filter(function(item) {
                    if (~this.disabledForReadOnlyActionList.indexOf(item.name)) {
                        return false;
                    }
                    return true;
                }, this)
            }

            this.setupActionList();
            this.setupButtonList();
        },

        actionList: [
            {
                name: 'refresh',
                label: 'Refresh',
                iconHtml: '<span class="fas fa-sync-alt"></span>',
            },
            {
                name: 'options',
                label: 'Options',
                iconHtml: '<span class="fas fa-pencil-alt fa-sm"></span>',
            },
            {
                name: 'remove',
                label: 'Remove',
                iconHtml: '<span class="fas fa-times"></span>',
            }
        ],

        buttonList: [],

        setupDefaultOptions: function () {},

        setupActionList: function () {},

        setupButtonList: function () {},

        getOption: function (key) {
            return this.optionsData[key];
        }

    });
});


