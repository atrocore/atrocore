/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/navigation/badge', 'view', function (Dep) {
    return Dep.extend({
        template: 'selection/navigation/badge',

        events: {
            'click a[data-action="showCurrentSelection"]': function (e) {
                if (!this.hasView('panel')) {
                    this.openBadge();
                } else {
                    this.closeBadge();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeBadge();
            }
        },

        openBadge: function () {
            this.closeBadge();

            this.$el.addClass('open');

            var $container = $('<div>').attr('id', 'notifications-panel');

            $container.appendTo(this.$el.find('.select-panel-container'));

            this.createView('panel', 'views/selection/navigation/panel', {
                el: `${this.options.el} .current-selection-panel-container`
            }, view => {
                this.listenTo(view, 'close', () => {
                    this.closeBadge();
                });
                view.render();
            });

            $(document).on('mouseup.select', function (e) {
                let container = this.$el.find('.current-selection-panel-container');
                if (!container.is(e.target) && container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    if(!$('.modal-container').length){
                        this.closeBadge();
                    }
                }
            }.bind(this));
        },

        closeBadge: function () {
            this.$el.removeClass('open');
            if (this.hasView('panel')) {
                this.clearView('panel');
            }
            $(document).off('mouseup.select');
        },
    })
});