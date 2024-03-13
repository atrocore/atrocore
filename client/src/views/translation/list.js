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

Espo.define('views/translation/list', 'views/list', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.menu.dropdown = [];

            this.menu.dropdown.push({
                acl: 'create',
                aclScope: 'Label',
                action: 'reset',
                label: this.translate('reset', 'labels', 'Translation'),
                iconHtml: ''
            });

            this.menu.dropdown.push({
                acl: 'create',
                aclScope: 'Label',
                action: 'push',
                label: this.translate('push', 'labels', 'Translation'),
                iconHtml: ''
            });
        },

        actionReset() {
            this.confirm({
                message: this.translate('resetConfirm', 'messages', 'Translation'),
                confirmText: this.translate('Apply')
            }, () => {
                this.ajaxPostRequest(`Translation/action/reset`).then(response => {
                    this.notify(this.translate('resetSuccessfully', 'messages', 'Translation'), 'success');
                });
            });
        },

        actionPush() {
            this.confirm({
                message: this.translate('pushConfirm', 'messages', 'Translation'),
                confirmText: this.translate('Apply')
            }, () => {
                this.ajaxPostRequest(`Translation/action/push`).then(success => {
                    if (success) {
                        this.notify(this.translate('pushSuccessfully', 'messages', 'Translation'), 'success');
                    } else {
                        this.notify(this.translate('pushFailed', 'messages', 'Translation'), 'error');
                    }

                });
            });
        },

    });
});

