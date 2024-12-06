/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */
Espo.define('views/bookmark/badge', 'view', function (Dep) {
    return Dep.extend({
        template: 'bookmark/badge',

        events: {
            'click a[data-action="showBookmark"]': function (e) {
                if (!this.hasView('panel')) {
                    this.showBookmark();
                } else {
                    this.closeBookmark();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeBookmark();
            }
        },

        showBookmark: function () {
            this.closeBookmark();

            this.$el.addClass('open');

            var $container = $('<div>').attr('id', 'notifications-panel');

            $container.appendTo(this.$el.find('.bookmark-panel-container'));

            this.createView('panel', 'views/bookmark/panel', {
                el: `${this.options.el} .bookmark-panel-container`
            }, view => {
                this.listenTo(view, 'closeBookmark', () => {
                    this.closeBookmark();
                });
                view.render();
            });

            $(document).on('mouseup.bookmark', function (e) {
                let container = this.$el.find('.bookmark-panel-container');
                if (!container.is(e.target) && container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    this.closeBookmark();
                }
            }.bind(this));
        },

        closeBookmark: function () {
            this.$el.removeClass('open');

            if (this.hasView('panel')) {
                this.clearView('panel');
            }

            $(document).off('mouseup.bookmark');
        },
    })
});