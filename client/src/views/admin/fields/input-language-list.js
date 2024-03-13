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

Espo.define('views/admin/fields/input-language-list', 'views/fields/multi-enum',
    Dep => Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.defineMode();
            this.listenTo(this.model, 'change:isMultilangActive', () => {
                this.defineMode();
                this.reRender();
            });

            this.listenTo(this.model, 'change:mainLanguage', () => {
                this.setupOptions();

                let inputLanguageList = [];
                (this.model.get('inputLanguageList') || []).forEach(locale => {
                    if (locale !== this.model.get('mainLanguage')) {
                        inputLanguageList.push(locale);
                    }
                })
                this.model.set('inputLanguageList', inputLanguageList);

                this.reRender();
            });
        },

        defineMode: function () {
            if (this.model.get('isMultilangActive')) {
                this.setMode('edit');
            } else {
                this.setMode('detail');
            }
        },

        data() {
            return _.extend({
                optionList: this.model.options || []
            }, Dep.prototype.data.call(this));
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            let translatedOptions = this.getLanguage().translate('language', 'options');
            this.getMetadata().get(['multilang', 'languageList']).forEach(locale => {
                if (locale !== this.model.get('mainLanguage')) {
                    this.params.options.push(locale);
                    this.translatedOptions[locale] = translatedOptions[locale] ?? locale;
                }
            });
        },

        translate(label, category, scope) {
            if (label === 'fieldIsRequired') {
                return Dep.prototype.translate.call(this, 'languageMustBeSelected', 'messages', 'Settings');
            }

            return Dep.prototype.translate.call(this, label, category, scope);
        }
    })
);
