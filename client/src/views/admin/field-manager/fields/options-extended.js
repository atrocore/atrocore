/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/options-extended', ['views/fields/array-extended', 'lib!jscolor'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {};
            (this.model.get(this.name) || []).forEach(function (value) {
                this.translatedOptions[value] = value;
            }, this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.$list.find('.color-input').get().forEach(item => {
                    new jscolor(item)

                    jscolor.init();
                });
            }
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
                data.translatedOptions[value] = value;
            }, this);

            return data;
        }

    });

});
