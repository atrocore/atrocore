/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/password-regex-pattern', 'views/fields/varchar-with-translation-sign', Dep => {
    return Dep.extend({
        setup: function () {
            Dep.prototype.setup.call(this);
            this.validations.push('regexpValid');
            this.applyDefaultValue();
        },

        getEntityScope() {
            return 'User';
        },

        getCategory() {
            return 'messages';
        },

        getEntityFieldName() {
            return 'newPasswordHint';
        },

        getIconClass() {
            return 'ph-globe-simple';
        },

        getIconTitle() {
            return this.translate('editPasswordHintLabel', 'messages', 'Settings');
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
