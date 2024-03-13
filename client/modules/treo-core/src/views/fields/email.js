/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/email', 'class-replace!treo-core:views/fields/email',

    Dep => Dep.extend({

        validateEmailData: function () {
            var data = this.model.get(this.dataFieldName);
            if (data && data.length) {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                var notValid = false;
                data.forEach(function (row, i) {
                    var emailAddress = row.emailAddress;
                    if (!re.test(emailAddress) && emailAddress.indexOf(this.erasedPlaceholder) !== 0) {
                        var msg = this.translate('fieldShouldBeEmail', 'messages').replace('{field}', this.getLabelText());
                        this.showValidationMessage(msg, 'div.email-address-block:nth-child(' + (i + 1).toString() + ') input');
                        this.setErrorClass('div.email-address-block:nth-child(' + (i + 1).toString() + ') input');
                        notValid = true;
                    }
                }, this);
                if (notValid) {
                    return true;
                }
            }
        },
        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.name) || !this.model.get(this.name) === '') {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, 'div.email-address-block:nth-child(1) input');
                    this.setErrorClass('div.email-address-block:nth-child(1) input');
                    return true;
                }
            }
        },
        setErrorClass(selector) {
            let input = this.$el.find(selector);
            input.addClass('email-data-error');

            this.$el.on('click', () => {
                input.removeClass('email-data-error');
                this.$el.off('click');
            });
        }
    })
);
