/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/queue-manager/panel', 'view', function (Dep) {

    return Dep.extend({

        interval: null,

        queueCheckInterval: 2,

        showDone: true,

        template: 'treo-core:queue-manager/panel',

        events: _.extend({
            'change input[name="showDone"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.collection.where = this.getWhere();
                this.collection.fetch();
            },
            'click [data-action="viewList"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.getRouter().navigate($(e.target).attr('href'), {trigger: true});
                this.trigger('closeQueue');
            },
            'click [data-action="start-qm"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.ajaxPostRequest('App/action/QueueManagerUpdate', {pause: false}).then(() => {
                    this.notify('Done', 'success');
                });
            },
            'click [data-action="pause-qm"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                this.ajaxPostRequest('App/action/QueueManagerUpdate', {pause: true}).then(() => {
                    this.notify('Done', 'success');
                });
            }
        }, Dep.prototype.events),

        data() {
            return {};
        },

        setup() {
            this.queueCheckInterval = this.getConfig().get('queueCheckInterval') || this.queueCheckInterval;

            this.wait(true);
            this.getCollectionFactory().create('QueueItem', collection => {
                this.collection = collection;
                this.collection.maxSize = 20;
                this.collection.url = 'QueueItem';
                this.collection.sortBy = 'sortOrder';
                this.collection.asc = true;
                this.collection.where = this.getWhere();

                this.listenTo(this.collection, 'reloadList', () => {
                    this.collection.fetch();
                });

                this.listenToOnce(this, 'after:render', () => this.initInterval());

                this.listenToOnce(this, 'remove', () => {
                    if (this.interval) {
                        window.clearInterval(this.interval);
                    }
                });

                this.wait(false);
            });

            this.listenTo(Backbone.Events, 'publicData', data => {
                this.$el.find('.qm-button').hide();
                if (this.getUser().isAdmin()) {
                    if (data.qmPaused) {
                        this.$el.find('.qm-button[data-action="start-qm"]').show();
                    } else {
                        this.$el.find('.qm-button[data-action="pause-qm"]').show();
                    }
                }
            });
        },

        afterRender() {
            this.$showDone = this.$el.find('input[name="showDone"]');

            this.$el.find('.qm-button').hide();

            this.listenToOnce(this.collection, 'sync', () => {
                let viewName = 'views/record/list';
                this.createView('list', viewName, {
                    el: this.options.el + ' .list-container',
                    collection: this.collection,
                    rowActionsDisabled: true,
                    checkboxes: false,
                    headerDisabled: true,
                    layoutName: 'listInQueueManager'
                }, function (view) {
                    view.render();
                });
            });
            this.collection.fetch();
        },

        initInterval() {
            this.interval = window.setInterval(() => {
                if (!this.isQueueModalShowed()) {
                    this.collection.fetch();
                }
            }, 1000 * this.queueCheckInterval);
        },

        isQueueModalShowed() {
            return $(document).find('.queue-modal').length;
        },

        getWhere() {
            return [
                {
                    field: 'status',
                    type: 'in',
                    value: ['Running', 'Pending']
                }
            ];
        },

    });

});
