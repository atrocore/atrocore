/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/thousand-separator', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        validations: ['thousandSeparator', 'value'],

        acceptableValues: ['.', ','],

        validateThousandSeparator: function () {
            if (this.model.get(this.name) == this.model.get('decimalMark')) {
                var msg = this.translate('thousandSeparatorEqualsDecimalMark', 'messages', 'Admin');
                this.showValidationMessage(msg);
                return true;
            }

        },

        validateValue: function () {
            const value = this.model.get(this.name)
            if (value && !this.acceptableValues.includes(value)) {
                var msg = this.translate('incorrectThousandSeparatorOrDecimalMark', 'messages', 'Admin');
                this.showValidationMessage(msg);
                return true;
            }
        }
    });

});
