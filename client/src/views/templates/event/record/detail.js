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

Espo.define('views/templates/event/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            if (this.getAcl().checkModel(this.model, 'edit')) {
                if (['Held', 'Not Held'].indexOf(this.model.get('status')) == -1) {
                    this.dropdownItemList.push({
                        'html': this.translate('Set Held', 'labels', this.scope),
                        'name': 'setHeld'
                    });
                    this.dropdownItemList.push({
                        'html': this.translate('Set Not Held', 'labels', this.scope),
                        'name': 'setNotHeld'
                    });
                }
            }
        },

        actionSetHeld: function () {
                this.model.save({
                    status: 'Held'
                }, {
                    patch: true,
                    success: function () {
                        Espo.Ui.success(this.translate('Saved', 'labels', 'Meeting'));
                        this.removeButton('setHeld');
                        this.removeButton('setNotHeld');
                    }.bind(this),
                });
        },

        actionSetNotHeld: function () {
                this.model.save({
                    status: 'Not Held'
                }, {
                    patch: true,
                    success: function () {
                        Espo.Ui.success(this.translate('Saved', 'labels', 'Meeting'));
                        this.removeButton('setHeld');
                        this.removeButton('setNotHeld');
                    }.bind(this),
                });
        },

    });
});

