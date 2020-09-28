

Espo.define('views/stream', 'view', function (Dep) {

    return Dep.extend({

        template: 'stream',

        filterList: ['all', 'posts', 'updates'],

        filter: false,

        events: {
            'click button[data-action="refresh"]': function () {
                if (!this.hasView('list')) return;
                this.getView('list').showNewRecords();
            },
            'click button[data-action="selectFilter"]': function (e) {
                var data = $(e.currentTarget).data();
                this.actionSelectFilter(data);
            },
        },

        data: function () {
            var filter = this.filter;
            if (filter === false) {
                filter = 'all';
            }
            return {
                displayTitle: this.options.displayTitle,
                filterList: this.filterList,
                filter: filter
            };
        },

        setup: function () {
            this.filter = this.options.filter || this.filter;

            this.wait(true);
            this.getModelFactory().create('Note', function (model) {
                this.createView('createPost', 'views/stream/record/edit', {
                    el: this.options.el + ' .create-post-container',
                    model: model,
                    interactiveMode: true
                }, function (view) {
                    this.listenTo(view, 'after:save', function () {
                        this.getView('list').showNewRecords();
                    }, this);
                }, this);
                this.wait(false);
            }, this);
        },

        afterRender: function () {
            this.notify('Loading...');
            this.getCollectionFactory().create('Note', function (collection) {
                this.collection = collection;
                collection.url = 'Stream';

                this.setFilter(this.filter);

                this.listenToOnce(collection, 'sync', function () {
                    this.createView('list', 'views/stream/record/list', {
                        el: this.options.el + ' .list-container',
                        collection: collection,
                        isUserStream: true,
                    }, function (view) {
                        view.notify(false);
                        view.render();
                    });
                }, this);
                collection.fetch();
            }, this);
        },

        actionSelectFilter: function (data) {
            var name = data.name;
            var filter = name;

            var internalFilter = name;

            if (filter == 'all') {
                internalFilter = false;
            }

            this.filter = internalFilter;
            this.setFilter(this.filter);

            this.filterList.forEach(function (item) {
                var $el = this.$el.find('.page-header button[data-action="selectFilter"][data-name="'+item+'"]');
                if (item === filter) {
                    $el.addClass('active');
                } else {
                    $el.removeClass('active');
                }
            }, this);

            var url = '#Stream';
            if (this.filter) {
                url += '/' + filter;
            }
            this.getRouter().navigate(url);

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            this.listenToOnce(this.collection, 'sync', function () {
                Espo.Ui.notify(false);
            }, this);

            this.collection.reset();
            this.collection.fetch();
        },

        setFilter: function (filter) {
            this.collection.data.filter = null;
            if (filter) {
                this.collection.data.filter = filter;
            }
            this.collection.offset = 0;
            this.collection.maxSize = this.getConfig().get('recordsPerPage') || this.collection.maxSize;
        },

    });
});

