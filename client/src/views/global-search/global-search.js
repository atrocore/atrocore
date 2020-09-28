

Espo.define('views/global-search/global-search', 'view', function (Dep) {

    return Dep.extend({

        template: 'global-search/global-search',

        events: {
            'keypress #global-search-input': function (e) {
                if (e.keyCode == 13) {
                    this.runSearch();
                }
            },
            'click [data-action="search"]': function () {
                this.runSearch();
            },
            'focus #global-search-input': function (e) {
                e.currentTarget.select();
            }
        },

        setup: function () {

            this.wait(true);
            this.getCollectionFactory().create('GlobalSearch', function (collection) {
                this.collection = collection;
                collection.name = 'GlobalSearch';
                this.wait(false);
            }, this);

        },

        afterRender: function () {
            this.$input = this.$el.find('#global-search-input');
        },

        runSearch: function (text) {
            var text = this.$input.val().trim();
            if (text != '' && text.length >= 2) {
                text = text;
                this.search(text);
            }
        },

        search: function (text) {
            this.collection.url = this.collection.urlRoot =  'GlobalSearch?q=' + encodeURIComponent(text);

            this.showPanel();
        },

        showPanel: function () {
            this.closePanel();

            var $container = $('<div>').attr('id', 'global-search-panel');

            $container.appendTo(this.$el.find('.global-search-panel-container'));

            this.createView('panel', 'views/global-search/panel', {
                el: '#global-search-panel',
                collection: this.collection,
            }, function (view) {
                view.render();
            }.bind(this));

            $document = $(document);
            $document.on('mouseup.global-search', function (e) {
                if (e.which !== 1) return;
                if (!$container.is(e.target) && $container.has(e.target).length === 0) {
                    this.closePanel();
                }
            }.bind(this));
            $document.on('click.global-search', function (e) {
                if (e.target.tagName == 'A' && $(e.target).data('action') != 'showMore') {
                    setTimeout(function () {
                        this.closePanel();
                    }.bind(this), 100);
                    return;
                }
            }.bind(this));
        },

        closePanel: function () {
            $container = $('#global-search-panel');

            $('#global-search-panel').remove();
            $document = $(document);
            if (this.hasView('panel')) {
                this.getView('panel').remove();
            };
            $document.off('mouseup.global-search');
            $document.off('click.global-search');
            $container.remove();
        }

    });
});
