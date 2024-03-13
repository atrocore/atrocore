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

Espo.define('views/translation/fields/module', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupTranslation() {
            const now = new Date();
            const key = 'installedModulesData';

            let data = localStorage.getItem(key);
            if (data) {
                data = JSON.parse(data);
            }

            if (!data || now.getTime() > data.expiry) {
                this.ajaxGetRequest(`Composer/list`, {}, {async: false}).then(response => {
                    data = {data: response.list, expiry: now.getTime() + 5 * 60 * 1000};
                    localStorage.setItem(key, JSON.stringify(data))
                });
            }

            this.params.options = [];
            this.translatedOptions = {};

            data.data.forEach(module => {
                let id = module.id === 'TreoCore' ? 'core' : module.id;

                this.params.options.push(id);
                this.translatedOptions[id] = module.name;
            });

            this.params.options.push('custom');
            this.translatedOptions['custom'] = 'Custom';
        },

    });
});

