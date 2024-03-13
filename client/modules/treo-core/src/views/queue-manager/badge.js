/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/badge', 'view',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/badge',

        events: {
            'click a[data-action="showQueue"]': function (e) {
                if (!this.hasView('panel')) {
                    this.showQueue();
                } else {
                    this.closeQueue();
                }
            },
            'click a[data-action="close"]': function () {
                this.closeQueue();
            }
        },

        afterRender() {
            this.listenTo(Backbone, 'showQueuePanel', () => {
                if (this.checkConditions()) {
                    this.showQueue();
                }
            });
        },

        showQueue() {
            this.closeQueue();

            this.$el.addClass('open');

            this.createView('panel', 'treo-core:views/queue-manager/panel', {
                el: `${this.options.el} .queue-panel-container`
            }, view => {
                this.listenTo(view, 'closeQueue', () => {
                    this.closeQueue();
                });
                view.render();
            });

            $(document).on('mouseup.queue', function (e) {
                let container = this.$el.find('.queue-panel-container');
                if (!container.is(e.target) && container.has(e.target).length === 0
                    && !this.$el.is(e.target) && this.$el.has(e.target).length === 0) {
                    this.closeQueue();
                }
            }.bind(this));
        },

        closeQueue() {
            this.$el.removeClass('open');

            if (this.hasView('panel')) {
                this.clearView('panel');
            }

            $(document).off('mouseup.queue');
        },

        checkConditions() {
            return (this.options.intervalConditions || []).every(item => {
                if (typeof item === 'function') {
                    return item();
                }
                return false;
            });
        }

    })
);
