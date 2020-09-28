

Espo.define('treo-core:views/queue-manager/badge', 'view',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/badge',

        events: {
            'click a[data-action="showQueue"]': function (e) {
                this.showQueue();
            },
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
                if (!container.is(e.target) && container.has(e.target).length === 0) {
                    this.closeQueue();
                }
            }.bind(this));
        },

        closeQueue() {
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
