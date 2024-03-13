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

Espo.define('views/admin/field-manager/fields/options', 'views/fields/array', function (Dep) {

    return Dep.extend({

        maxItemLength: 100,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {};
            var list = this.model.get(this.name) || [];
            list.forEach(function (value) {
                this.translatedOptions[value] = this.getLanguage().translateOption(value, this.options.field, this.options.scope);
            }, this);

            this.model.fetchedAttributes.translatedOptions = this.translatedOptions;
        },

        getItemHtml: function (value) {
            var valueSanitized = this.getHelper().stripTags(value);
            var translatedValue = this.translatedOptions[value] || valueSanitized;

            translatedValue = translatedValue.replace(/"/g, '&quot;');

            var valueInternal = valueSanitized.replace(/"/g, '-quote-');

            var html = '' +
            '<div class="list-group-item link-with-role form-inline" data-value="' + valueInternal + '">' +
                '<div class="pull-left" style="width: 92%; display: inline-block;">' +
                    '<input name="translatedValue" data-value="' + valueInternal + '" class="role form-control input-sm pull-right" value="'+translatedValue+'">' +
                    '<div>' + valueSanitized + '</div>' +
                '</div>' +
                '<div style="width: 8%; display: inline-block; vertical-align: top;">' +
                    '<a href="javascript:" class="pull-right" data-value="' + valueInternal + '" data-action="removeValue"><span class="fas fa-times"></a>' +
                '</div><br style="clear: both;" />' +
            '</div>';

            return html;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            if (!data[this.name].length) {
                data[this.name] = false;
                data.translatedOptions = {};
                return data;
            }

            data.translatedOptions = {};
            (data[this.name] || []).forEach(function (value) {
                var valueSanitized = this.getHelper().stripTags(value);
                var valueInternal = valueSanitized.replace(/"/g, '-quote-');
                var translatedValue = this.$el.find('input[name="translatedValue"][data-value="'+valueInternal+'"]').val() || value;

                translatedValue = translatedValue.toString();

                data.translatedOptions[value] = translatedValue;
            }, this);

            return data;
        }

    });

});
