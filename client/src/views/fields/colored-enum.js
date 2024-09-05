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

Espo.define('views/fields/colored-enum', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        listTemplate: 'fields/colored-enum/detail',

        detailTemplate: 'fields/colored-enum/detail',

        editTemplate: 'fields/colored-enum/edit',

        defaultBackgroundColor: 'ececec',

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.$el.find(`select[name="${this.name}"]`).on('change', function () {
                    this.$element.css(this.getFieldStyles(this.$element.val()));
                }.bind(this));
            }
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.options = (data.params.options || []).map(item => {
                return _.extend({
                    selected: item === data.value,
                    value: item
                }, this.getFieldStyles(item));
            });
            data = _.extend(this.getFieldStyles(data.value), data);

            return data;
        },

        getBorder(col) {
            let amt = -10;
            let num = parseInt(col.slice(1), 16);
            let r = (num >> 16) + amt;

            if (r > 255) {
                r = 255;
            } else if (r < 0) {
                r = 0;
            }

            let b = ((num >> 8) & 0x00FF) + amt;

            if (b > 255) {
                b = 255;
            } else if (b < 0) {
                b = 0;
            }

            let g = (num & 0x0000FF) + amt;

            if (g > 255) {
                g = 255;
            } else if (g < 0) {
                g = 0;
            }

            let color = (g | (b << 8) | (r << 16)).toString(16);
            while (color.length < 6) {
                color = '0' + color;
            }

            return "1px solid #" + color;
        },

        getFieldStyles(fieldValue) {
            const backgroundColor = this.getBackgroundColor(fieldValue);
            const fontSize = this.model.getFieldParam(this.name, 'fontSize');

            let data = {
                fontWeight: 'normal',
                backgroundColor: backgroundColor,
                color: this.getFontColor(backgroundColor),
                border: this.getBorder(backgroundColor)
            };

            if (this.mode !== 'edit') {
                data.fontSize = fontSize ? fontSize + 'em' : '100%';
            }

            return data;
        },

        getBackgroundColor(fieldValue) {
            let options = this.model.getFieldParam(this.name, 'options') || this.params.options || [];
            let optionColors = this.model.getFieldParam(this.name, 'optionColors') || this.params.optionColors || [];

            if (!Array.isArray(optionColors)) {
                optionColors = [];
            }

            let key = 0;
            options.forEach(function (item, k) {
                if (fieldValue === item) {
                    key = k;
                }
            });

            let result = (optionColors[key] || this.defaultBackgroundColor);
            if (result.indexOf('#') < 0) {
                result = '#' + result;
            }

            return result;
        },

        getFontColor(backgroundColor) {
            let color = '#000';
            if (backgroundColor) {
                backgroundColor = backgroundColor.slice(1);
                let r = parseInt(backgroundColor.substr(0, 2), 16);
                let g = parseInt(backgroundColor.substr(2, 2), 16);
                let b = parseInt(backgroundColor.substr(4, 2), 16);
                let l = 1 - (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                if (l >= 0.5) {
                    color = '#fff';
                }
            }
            return color;
        }
    });

});
