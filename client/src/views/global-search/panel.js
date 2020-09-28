

Espo.define('views/global-search/panel', 'view', function (Dep) {

    return Dep.extend({

        template: 'global-search/panel',

        setup: function () {
            this.maxSize = this.getConfig().get('globalSearchMaxSize') || 10;

            this.navbarPanelHeightSpace = this.getThemeManager().getParam('navbarPanelHeightSpace') || 100;
            this.navbarPanelBodyMaxHeight = this.getThemeManager().getParam('navbarPanelBodyMaxHeight') || 600;
        },

        afterRender: function () {
            this.listenToOnce(this.collection, 'sync', function () {
                this.createView('list', 'views/record/list-expanded', {
                    el: this.options.el + ' .list-container',
                    collection: this.collection,
                    listLayout: {
                        rows: [
                            [
                                {
                                    name: 'name',
                                    view: 'views/global-search/name-field',
                                    params: {
                                        containerEl: this.options.el
                                    }
                                }
                            ]
                        ],
                        right: {
                            name: 'read',
                            view: 'views/global-search/scope-badge',
                            width: '80px'
                        }
                    }
                }, function (view) {
                    view.render();
                });
            }, this);

            this.collection.reset();
            this.collection.maxSize = this.maxSize;
            this.collection.fetch();

            var windowHeight = $(window).height();
            if (windowHeight - this.navbarPanelBodyMaxHeight < this.navbarPanelHeightSpace) {
                var maxHeight = windowHeight - this.navbarPanelHeightSpace;
                this.$el.find('> .panel > .panel-body').css('maxHeight', maxHeight + 'px');
            }
        }

    });
});
