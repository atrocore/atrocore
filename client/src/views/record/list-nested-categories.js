

Espo.define('views/record/list-nested-categories', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/list-nested-categories',

        events: {
            'click .action': function (e) {
                var $el = $(e.currentTarget);
                var action = $el.data('action');
                var method = 'action' + Espo.Utils.upperCaseFirst(action);
                if (typeof this[method] == 'function') {
                    var data = $el.data();
                    this[method](data, e);
                    e.preventDefault();
                }
            },
        },

        data: function () {
            var data = {};

            if (!this.isLoading) {
                data.list = this.getDataList();
            }
            data.scope = this.collection.name;
            data.isLoading = this.isLoading;

            return data;
        },

        getDataList: function () {
            var list = [];
            this.collection.forEach(function (model) {
                var o = {
                    id: model.id,
                    name: model.get('name'),
                    recordCount: model.get('recordCount'),
                    isEmpty: model.get('isEmpty')
                };
                list.push(o);
            }, this);
            return list;
        },

        setup: function () {
            this.listenTo(this.collection, 'sync', function () {
                this.reRender();
            }, this);
        },

        actionShowMore: function () {
            this.$el.find('.category-item.show-more').addClass('hidden');

            this.collection.fetch({
                remove: false,
                more: true
            });
        }

    });
});
