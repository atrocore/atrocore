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

Espo.define('views/fields/overview-locales-filter', 'views/fields/dropdown-enum',
    Dep => Dep.extend({

        optionsList: [],

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'overview-filters-changed', name => {
                if (name === 'showAllLanguages') {
                    this.getView('showLanguageFields_main').$el.prop('checked', true);
                    this.model.advancedEntityView.showLanguageFields_main = true;

                    console.log(this.model)
                    // console.log(this.getView('showLanguageFields_main').$el)
                }
            });
        },

        prepareOptionsList() {
            this.optionsList.push(
                {
                    name: 'showAllLanguages',
                    selectable: true,
                    label: this.translate("allLanguages"),
                }
            );
            this.optionsList.push(
                {
                    name: "showLanguageFields_main",
                    action: "showLanguageFields_main",
                    field: true,
                    type: "bool",
                    label: this.translate("mainLanguage"),
                    view: "views/fields/bool-with-inline-label",
                    default: true
                }
            );

            if (this.getConfig().get('isMultilangActive')) {
                (this.getConfig().get('inputLanguageList') || []).forEach(locale => {
                    this.optionsList.push(
                        {
                            name: "showLanguageFields_" + locale,
                            action: "showLanguageFields_" + locale,
                            field: true,
                            type: "bool",
                            label: this.getLanguage().translateOption(locale, 'language', 'Global').split(' ›').shift(),
                            view: "views/fields/bool-with-inline-label",
                            default: true
                        }
                    );
                });
            }

            Dep.prototype.prepareOptionsList.call(this);
        },

    })
);