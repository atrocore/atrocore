/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/connection/fields/smtp-port', 'views/fields/int', function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:smtpSecurity', function () {
                const smtpSecurity = this.model.get('smtpSecurity');
                if (smtpSecurity === 'SSL') {
                    this.model.set('smtpPort', '465');
                } else if (smtpSecurity === 'TLS') {
                    this.model.set('smtpPort', '587');
                } else {
                    this.model.set('smtpPort', '25');
                }
            }.bind(this))
        }
    });
});
