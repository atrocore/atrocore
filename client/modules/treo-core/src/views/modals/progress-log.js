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
 */

Espo.define('treo-core:views/modals/progress-log', 'views/modal',
    Dep => Dep.extend({

        template: 'treo-core:modals/progress-log',

        inProgress: false,

        log: null,

        data() {
            return {
                logData: this.log,
                inProgress: this.inProgress
            };
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.setupHeader();
            this.setupButtonList();

            this.setupProgressData(this.options.progressData);
            this.listenTo(this, 'log-updated', progressData => {
                this.setupProgressData(progressData);
                this.reRender();
            });
        },

        setupProgressData(progressData) {
            if (progressData) {
                this.log = progressData.log;
                this.inProgress = progressData.inProgress;
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            let spinner = this.$el.find('.spinner');
            this.inProgress ? spinner.removeClass('hidden') : spinner.addClass('hidden');
        },

        setupHeader() {
            this.header = this.translate('progressLog', 'labels', 'Admin');
        },

        setupButtonList() {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];
        },

    })
);