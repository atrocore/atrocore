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

Espo.define('views/fields/color', ['views/fields/varchar', 'views/fields/colored-enum', 'lib!jscolor'],
    (Dep, ColoredEnum) => Dep.extend({

        listTemplate: 'fields/colored-enum/detail',

        detailTemplate: 'fields/colored-enum/detail',

        editTemplate: 'fields/base/edit',

        data: function () {
            let data = Dep.prototype.data.call(this);

            if (this.mode !== 'edit') {
                const fontSize = this.model.getFieldParam(this.name, 'fontSize');

                data['fontWeight'] = 'normal';
                data['fontSize'] = fontSize ? fontSize + 'em' : '100%';
                data['backgroundColor'] = this.model.get(this.name) || '#ececec'
                data['color'] = ColoredEnum.prototype.getFontColor.call(this, data['backgroundColor']);
                data['border'] = ColoredEnum.prototype.getBorder.call(this, data['backgroundColor']);
            }

            return data;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let input = this.$el.find('input').get(0);

            if (input) {
                let options = {zIndex: 2000, required: false};

                if (this.model && this.model.name) {
                    const defs = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name]) || [];

                    if (defs['prohibitedEmptyValue']) {
                        options.required = true;
                    }
                }

                let picker = new jscolor(input, options);

                jscolor.init();
                if (this.mode !== 'edit') {
                    input.readOnly = true;
                    picker.showOnClick = false;
                }
            }
        },

    })
);
