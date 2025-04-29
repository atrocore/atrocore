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
        events:_.extend({
            'click a[data-action="expandDetails"]': function (e) {
                if (this.$el.find('.details').hasClass('hidden')) {
                    this.$el.find('.details').removeClass('hidden');
                    $(e.currentTarget).find('i').removeClass('ph-caret-down').addClass('ph-caret-up');
                } else {
                    this.$el.find('.details').addClass('hidden');
                    $(e.currentTarget).find('i').addClass('ph-caret-down').removeClass('ph-caret-up');
                }
            }
        },Dep.prototype.events),

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

