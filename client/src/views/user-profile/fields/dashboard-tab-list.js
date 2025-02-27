/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/fields/dashboard-tab-list', 'views/fields/array', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {};
            var list = this.model.get(this.name) || [];
            list.forEach(function (value) {
                this.translatedOptions[value] = value;
            }, this);
        },

        getItemHtml: function (value) {
            var translatedValue = this.translatedOptions[value] || value;

            var html = '' +
            '<div class="list-group-item link-with-role form-inline" data-value="' + value + '">' +
                '<div class="pull-left" style="width: 92%; display: inline-block;">' +
                    '<input name="translatedValue" data-value="' + value + '" class="role form-control input-sm" value="'+translatedValue+'">' +
                '</div>' +
                '<div style="width: 8%; display: inline-block; vertical-align: top;">' +
                    '<a href="javascript:" class="pull-right" data-value="' + value + '" data-action="removeValue"><span class="fas fa-times"></a>' +
                '</div><br style="clear: both;" />' +
            '</div>';

            return html;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);
            data.translatedOptions = {};
            (data[this.name] || []).forEach(function (value) {
                data.translatedOptions[value] = this.$el.find('input[name="translatedValue"][data-value="'+value+'"]').val() || value;
            }, this);

            return data;
        }

    });

});
