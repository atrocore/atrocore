/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/password-regex-pattern', 'views/fields/varchar', function (Dep) {
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

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.on('keyup', e => {
                let value = e.target.value ?? '';
                if (value.startsWith('/')) {
                    value = value.slice(1);
                }

                if (value.endsWith('/')) {
                    value = value.slice(0, -1);
                }

                if (value) {
                    this.model.set(this.name, `/${value}/`, { silent: true });
                    e.target.value = `/${value}/`;
                } else {
                    this.model.set(this.name, '', { silent: true });
                    e.target.value = '';
                    return;
                }

                this.handleTextCursor(e.target);
            }).on('mouseup', e => {
                this.handleTextCursor(e.target);
            });
        },

        handleTextCursor(target) {
            if (target.selectionStart === target.value.length) {
                target.selectionStart -= 1;
                target.selectionEnd = target.selectionStart;
            }

            if (target.selectionStart === 0) {
                target.selectionStart = 1;
            }

            if (target.selectionEnd === target.value.length) {
                target.selectionEnd -= 1;
            }
        },

        getTooltipText: function () {
            let link = null;
            this.ajaxGetRequest(`Translation`, {
                where: [
                    {
                        type: "equals",
                        attribute: "name",
                        value: 'User.messages.newPasswordInvalid'
                    }
                ]
            }, {async: false}).then(res => {
                if (res.list && Array.isArray(res.list) && res.list.length > 0) {
                    link = `#Translation/edit/${res.list[0].id}`;
                }
            });

            if (!link) {
                console.error('Message User.messages.newPasswordInvalid is not available');
            }

            return (Dep.prototype.getTooltipText.call(this) ?? '').replace('{message_link}', link ?? '/');
        },

        validateRegexpValid: function () {
            let value = this.model.get(this.name);
            if (!value) return false;

            if (value.startsWith('/')) {
                value = value.slice(1);
            }

            if (value.endsWith('/')) {
                value = value.slice(0, -1);
            }

            try {
                const regex = new RegExp(value);
            } catch (e) {
                this.showValidationMessage(this.translate('regexNotValid', 'exceptions', 'FieldManager'));
                return true;
            }

            return false;
        }
    });
});