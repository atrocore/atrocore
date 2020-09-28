

Espo.define('views/role/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        tableView: 'views/role/record/table',

        sideView: false,

        isWide: true,

        columnCount: 3,

        events: _.extend({

        }, Dep.prototype.events),

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            data['data'] = {};

            var scopeList = this.getView('extra').scopeList;
            var actionList = this.getView('extra').actionList;
            var aclTypeMap = this.getView('extra').aclTypeMap;

            for (var i in scopeList) {
                var scope = scopeList[i];
                if (this.$el.find('select[name="' + scope + '"]').val() == 'not-set') {
                    continue;
                }
                if (this.$el.find('select[name="' + scope + '"]').val() == 'disabled') {
                    data['data'][scope] = false;
                } else {
                    var o = true;
                    if (aclTypeMap[scope] != 'boolean') {
                        o = {};
                        for (var j in actionList) {
                            var action = actionList[j];
                            o[action] = this.$el.find('select[name="' + scope + '-' + action + '"]').val();
                        }
                    }
                    data['data'][scope] = o;
                }
            }

            data['data'] = this.getView('extra').fetchScopeData();
            data['fieldData'] = this.getView('extra').fetchFieldData();

            return data;
        },

        getDetailLayout: function (callback) {
            var simpleLayout = [
                {
                    label: '',
                    cells: [
                        {
                            name: 'name',
                            type: 'varchar',
                        },
                    ]
                }
            ];
            callback({
                type: 'record',
                layout: this._convertSimplifiedLayout(simpleLayout)
            });
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('extra', this.tableView, {
                mode: 'edit',
                el: this.options.el + ' .extra',
                model: this.model
            }, function (view) {
                this.listenTo(view, 'change', function () {
                    var data = this.fetch();
                    this.model.set(data);
                }, this);
            }, this);
        }

    });
});


