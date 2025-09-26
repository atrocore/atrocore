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

Espo.define('views/entity-field/modals/update-code', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'entity-field/modals/update-code',

        setup: function () {
            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary'
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.header = this.translate('updateCode');

            var model = new Model();

            if(this.options.code) {
                model.set('code', this.options.code)
            }


            this.createView('code', 'views/fields/varchar', {
                el: this.options.el + ' .field[data-name="code"]',
                defs: {
                    name: 'code',
                    params: {
                        required: true
                    }
                },
                mode: 'edit',
                model: model
            });
        },

        actionSave: function () {
            var codeView = this.getView('code');
            codeView.fetchToModel();
            if (codeView.validate()) {
                return;
            }
            this.trigger('save', {
                code: codeView.model.get('code')
            });
            return true;
        },
    });
});


