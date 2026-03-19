/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/password-regex-pattern', ['views/fields/varchar', 'lib!MD5'], (Dep, MD5) => {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.validations.push('regexpValid');
            this.applyDefaultValue();
        },

        applyDefaultValue: function () {
            let defaultValue = this.model.defaults[this.name]
            if (defaultValue == null) {
                defaultValue = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'default'])
            }

            const value = this.getConfig().get(this.name, undefined);

            if (value === undefined && defaultValue) {
                this.model.set(this.name, defaultValue)
            }
        },

        getTooltipText: function () {
            let link = '#Translation/edit/' + MD5('User.messages.newPasswordInvalid');

            return (Dep.prototype.getTooltipText.call(this) ?? '').replace('{message_link}', link);
        },

        validateRegexpValid: function () {
            let value = this.model.get(this.name);
            if (!value) return false;

            try {
                new RegExp(value);
            } catch (e) {
                this.showValidationMessage(this.translate('regexSyntaxError', 'exceptions', 'FieldManager').replace('%s', this.name));
                return true;
            }

            return false;
        }
    });
});
