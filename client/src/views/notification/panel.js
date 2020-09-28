

Espo.define('views/notification/panel', 'view', function (Dep) {

    return Dep.extend({

        template: 'notification/panel',

        events: {
            'click [data-action="markAllNotificationsRead"]': function () {
                $.ajax({
                    url: 'Notification/action/markAllRead',
                    type: 'POST'
                }).done(function (count) {
                    this.trigger('all-read');
                }.bind(this));
            },
            'click [data-action="openNotifications"]': function (e) {
                this.getRouter().navigate('#Notification', {trigger: true});
                this.remove();
            }
        },

        setup: function () {
            this.wait(true);
            this.getCollectionFactory().create('Notification', function (collection) {
                this.collection = collection;
                collection.maxSize = this.getConfig().get('notificationsMaxSize') || 5;
                this.wait(false);
            }, this);

            this.navbarPanelHeightSpace = this.getThemeManager().getParam('navbarPanelHeightSpace') || 100;
            this.navbarPanelBodyMaxHeight = this.getThemeManager().getParam('navbarPanelBodyMaxHeight') || 600;
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
                                    }
                                }
                            ]
                        ],
                        right: {
                            name: 'read',
                            view: 'views/notification/fields/read',
                            width: '10px'
                        }
                    }
                }, function (view) {
                    view.render();
                });
            }, this);
            this.collection.fetch();

            var windowHeight = $(window).height();
            if (windowHeight - this.navbarPanelBodyMaxHeight < this.navbarPanelHeightSpace) {
                var maxHeight = windowHeight - this.navbarPanelHeightSpace;
                this.$el.find('> .panel > .panel-body').css('maxHeight', maxHeight + 'px');
            }
        }

    });
});
