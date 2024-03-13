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

Espo.define('views/module-manager/fields/name', 'views/fields/varchar', Dep => {

    return Dep.extend({

        listTemplate: 'module-manager/fields/name/list',

        data() {
            let data = Dep.prototype.data.call(this);

            if (this.mode === 'list') {
                let usage = this.model.get('usage');
                let expirationDate = this.model.get('expirationDate');

                if (usage && expirationDate) {
                    let preparedDate = this.getDateTime().toDisplayDate(expirationDate);
                    if (usage === 'Purchase') {
                        data.purchaseInfo = `${this.translate('purchased', 'labels', 'Composer')} · ${preparedDate}`;
                        data.purchaseTitle = this.translate('purchasedUntil', 'labels', 'Composer') + ' ' + preparedDate;
                    } else if (usage === 'Rent') {
                        data.purchaseInfo = `${this.translate('rented', 'labels', 'Composer')} · ${preparedDate}`;
                        data.purchaseTitle = this.translate('rentUntil', 'labels', 'Composer') + ' ' + preparedDate;
                    }

                    if (data.purchaseInfo && this.getDateTime().getToday() > expirationDate) {
                        data.expired = true;
                    }
                }
            }

            return data;
        },

    });
});

