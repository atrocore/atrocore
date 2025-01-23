/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/decimal-mark', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        validations: ['required', 'decimalMark', 'value'],

        acceptableValues: ['.', ','],

        validateDecimalMark: function () {
            if (this.model.get(this.name) == this.model.get('thousandSeparator')) {
                var msg = this.translate('thousandSeparatorEqualsDecimalMark', 'messages', 'Admin');
                this.showValidationMessage(msg);
                return true;
            }

        },

        validateValue: function () {
            if (!this.acceptableValues.includes(this.model.get(this.name))) {
                var msg = this.translate('incorrectThousandSeparatorOrDecimalMark', 'messages', 'Admin');
                this.showValidationMessage(msg);
                return true;
            }
        }
    });

});
