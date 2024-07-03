/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user/password-expired', 'views/user/password-change-request', function (Dep) {

    return Dep.extend({
        data: function () {
            return {};
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            $('.panel-body').prepend(`<p style="margin: 0 0 2rem;">${this.translate('passwordExpiredForm', 'messages', 'User')}</p>`);
        },

        sendRequest(password) {
            $.ajax({
                type: 'POST',
                url: 'User/action/changeExpiredPassword',
                data: JSON.stringify({
                    password: password
                }),
                error: this.onRequestError.bind(this)
            }).done(this.onRequestDone.bind(this));
        }
    });
});

