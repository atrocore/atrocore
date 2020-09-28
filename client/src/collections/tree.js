
Espo.define('collections/tree', 'collection', function (Dep) {

    return Dep.extend({

        createSeed: function () {
            var seed = new this.constructor();
            seed.url = this.url;
            seed.model = this.model;
            seed._user = this._user;
            seed.name = this.name;

            return seed;
        },

        parse: function (response) {
            var list = Dep.prototype.parse.call(this, response);

            var seed = this.clone();
            seed.reset();

            var f = function (l, depth) {
                l.forEach(function (d) {
                    d.depth = depth;
                    var c = this.createSeed();
                    if (d.childList) {
                        if (d.childList.length) {
                            f(d.childList, depth + 1);
                            c.set(d.childList);
                            d.childCollection = c;
                        } else {
                            d.childCollection = c;
                        }
                    } else if (d.childList === null) {
                        d.childCollection = null;
                    } else {
                        d.childCollection = c;
                    }
                }, this);
            }.bind(this);

            f(list, 0);

            return list;
        },

        fetch: function (options) {
            var options = options || {};
            options.data = options.data || {};

            if (this.parentId) {
                options.data.parentId = this.parentId;
            }
            options.data.maxDepth = this.maxDepth;

            return Dep.prototype.fetch.call(this, options);
        }

    });

});
