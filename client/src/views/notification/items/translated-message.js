/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/notification/items/translated-message', 'views/notification/items/base', function (Dep) {

    return Dep.extend({

        template: 'notification/items/translated-message',

        data: function () {
            return _.extend({
                style: this.style,
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            var data = this.model.get('data') || {};

            this.style = data.style || 'text-muted';

            let language = this.getPreferences().get('language');
            let mainLanguage = this.getConfig().get('mainLanguage');

            this.messageTemplate = data[language] ?? data[mainLanguage] ?? ''

            this.userId = data.userId;

            this.createMessage();
        }

    });
});

