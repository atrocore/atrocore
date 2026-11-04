/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * A number chosen from the list of the "options" of the field. The value is an integer or a
 * float, according to the type of the field.
 */
Espo.define('views/fields/number-option', 'views/fields/enum', Dep => Dep.extend({

    listTemplate: 'fields/enum/detail',

    detailTemplate: 'fields/enum/detail',

    editTemplate: 'fields/enum/edit',

    searchTemplate: 'fields/enum/search',

    validations: [],

    setup() {
        Dep.prototype.setup.call(this);

        (this.params.options || []).forEach(value => {
            this.translatedOptions[value] = value.toString();
        });
    },

    fetch() {
        const data = {};
        data[this.name] = this.parseValue(this.$el.find(`[name="${this.name}"]`).val());

        return data;
    },

    parseItemForSearch(item) {
        return this.parseValue(item);
    },

    parseValue(value) {
        return this.model.getFieldParam(this.name, 'type') === 'float' ? parseFloat(value) : parseInt(value);
    },

}));
