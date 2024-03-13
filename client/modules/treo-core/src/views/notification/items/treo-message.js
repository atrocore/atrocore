
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/notification/items/treo-message', 'views/notification/items/message', function (Dep) {

    return Dep.extend({
        setup: function () {
            // prepare data
            var data = this.model.get('data') || {};

            // prepare style
            this.style = data.style || 'text-muted';

            // prepare user
            this.userId = data.userId;

            // prepare message
            this.messageTemplate = '';
            if (typeof data.messageTemplate != 'undefined') {
                var message = this.translate(data.messageTemplate, 'treoNotifications', 'TreoNotification');
                if (typeof data.messageVars != 'undefined') {
                    $.each(data.messageVars, function (k, v) {
                        message = message.replace("{{" + k + "}}", v);
                    })
                }
                this.messageTemplate = message;
            }

            this.createMessage();
        }
    });
});

