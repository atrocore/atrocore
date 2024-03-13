/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/currency', 'class-replace!treo-core:views/fields/currency',
    Dep => Dep.extend({

        detailTemplate: 'treo-core:fields/currency/detail',

        detailTemplate1: 'treo-core:fields/currency/detail-1',

        detailTemplate2: 'treo-core:fields/currency/detail-2',

        listTemplate: 'treo-core:fields/currency/list',

        listTemplate1: 'treo-core:fields/currency/list-1',

        listTemplate2: 'treo-core:fields/currency/list-2',

        data() {
            let data = Dep.prototype.data.call(this);
            data.valueAndCurrency = !!(data.value && data.currencyValue);
            return data;
        },

        validateFloat: function () {
            if (this.$currency && !this.$currency.val()) {
                return true;
            }

            return Dep.prototype.validateFloat.call(this);
        },

        fetch: function () {
            let value = null;
            if (this.$element) {
                value = this.parse(this.$element.val());
            }

            let currency = null;
            if (this.$currency) {
                currency = this.$currency.val();
            }

            let data = {};
            data[this.name] = value;
            data[this.currencyFieldName] = currency;

            return data;
        },

    })
);