

Espo.define('Views.Fields.Followers', 'Views.Fields.LinkMultiple', function (Dep) {

    return Dep.extend({

        foreignScope: 'User',

        portionSize: 4,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.limit = this.portionSize;

            this.listenTo(this.model, 'change:isFollowed', function () {
                if (this.model.get('isFollowed')) {
                    var idList = this.model.get(this.idsName) || [];
                    if (!~idList.indexOf(this.getUser().id)) {
                        idList.unshift(this.getUser().id);
                        var nameMap = this.model.get(this.nameHashName) || {};

                        nameMap[this.getUser().id] = this.getUser().get('name');
                        this.model.trigger('change:' + this.idsName);
                        this.render();
                    }
                } else {
                    var idList = this.model.get(this.idsName) || [];
                    var index = idList.indexOf(this.getUser().id);
                    if (~index) {
                        idList.splice(index, 1);
                        this.model.trigger('change:' + this.idsName);
                        this.render();
                    }
                }
            }, this);

            this.events['click [data-action="showMoreFollowers"]'] = function (e) {
                this.showMoreFollowers();
                $(e.currentTarget).remove();
            };
        },

        reloadFollowers: function () {
            this.getCollectionFactory().create('User', function (collection) {
                collection.url = this.model.name + '/' + this.model.id + '/followers';
                collection.offset = 0;
                collection.maxSize = this.limit;

                this.listenToOnce(collection, 'sync', function () {
                    var idList = [];
                    var nameMap = {};
                    collection.forEach(function (user) {
                        idList.push(user.id);
                        nameMap[user.id] = user.get('name');
                    }, this);
                    this.model.set(this.idsName, idList);
                    this.model.set(this.nameHashName, nameMap);
                    this.render();
                }, this);

                collection.fetch();
            }, this);
        },

        showMoreFollowers: function () {
            this.getCollectionFactory().create('User', function (collection) {
                collection.url = this.model.name + '/' + this.model.id + '/followers';
                collection.offset = this.ids.length || 0;
                collection.maxSize = this.portionSize;

                this.listenToOnce(collection, 'sync', function () {
                    var idList = this.model.get(this.idsName) || [];
                    var nameMap = this.model.get(this.nameHashName) || {};
                    collection.forEach(function (user) {
                        idList.push(user.id);
                        nameMap[user.id] = user.get('name');
                    }, this);

                    this.limit += this.portionSize;

                    this.model.trigger('change:' + this.idsName);
                    this.render();
                }, this);

                collection.fetch();
            }, this);
        },

        getValueForDisplay: function () {
            if (this.mode == 'detail' || this.mode == 'list') {
                var list = [];
                this.ids.forEach(function (id) {
                    list.push(this.getDetailLinkHtml(id));
                }, this);
                var str = null;
                if (list.length) {
                    str = '' + list.join(', ') + '';
                }
                if (list.length >= this.limit) {
                    str += ', <a href="javascript:" data-action="showMoreFollowers">...</a>'
                }
                return str;
            }
        },

    });
});


