

Espo.define('views/notification/list', 'view', function (Dep) {

    return Dep.extend({

        template: 'notification/list',

        events: {
            'click [data-action="markAllNotificationsRead"]': function () {
                $.ajax({
                    url: 'Notification/action/markAllRead',
                    type: 'POST'
                }).done(function (count) {
                    this.trigger('all-read');
                    this.$el.find('.badge-circle-warning').remove();
                }.bind(this));
            },
            'click [data-action="refresh"]': function () {
                this.getView('list').showNewRecords();
            },
        },

        setup: function () {
            this.wait(true);
            this.getCollectionFactory().create('Notification', function (collection) {
                this.collection = collection;
                collection.maxSize = this.getConfig().get('recordsPerPage') || 20;
                this.wait(false);
            }, this);
        },

        afterRender: function () {
            this.listenToOnce(this.collection, 'sync', function () {
                var viewName = this.getMetadata().get(['clientDefs', 'Notification', 'recordViews', 'list']) || 'views/notification/record/list';
                this.createView('list', viewName, {
                    el: this.options.el + ' .list-container',
                    collection: this.collection,
                    showCount: false,
                    listLayout: {
                        rows: [
                            [
                                {
                                    name: 'data',
                                    view: 'views/notification/fields/container',
                                    params: {
                                        containerEl: this.options.el
                                    },
                                }
                            ]
                        ],
                        right: {
                            name: 'read',
                            view: 'views/notification/fields/read-with-menu',
                            width: '10px'
                        }
                    }
                }, function (view) {
                    view.render();
                });
            }, this);
            this.collection.fetch();
        }

    });

});
