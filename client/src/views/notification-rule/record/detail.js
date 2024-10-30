/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/notification-rule/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'after:save', () => {
                setTimeout(() => {
                    if (!this.getConfig().get('notificationSmtpConnectionId') && this.model.get('emailActive')) {
                        this.notify('youMustConfigureNotificationConnection', 'warning', 2000)
                    }
                }, 200);
            })
        },

    });
});

