

Espo.define('views/record/list-expanded', 'views/record/list', function (Dep) {

    return Dep.extend({

        template: 'record/list-expanded',

        checkboxes: false,

        selectable: false,

        rowActionsView: false,

        _internalLayoutType: 'list-row-expanded',

        presentationType: 'expanded',

        pagination: false,

        header: false,

        _internalLayout: null,

        checkedList: null,

        listContainerEl: '.list > ul',

        _loadListLayout: function (callback) {
            var type = this.type + 'Expanded';

            this.layoutLoadCallbackList.push(callback);

            if (this.layoutIsBeingLoaded) return;

            this.layoutIsBeingLoaded = true;
            this._helper.layoutManager.get(this.collection.name, type, function (listLayout) {
                this.layoutLoadCallbackList.forEach(function (c) {
                    c(listLayout)
                    this.layoutLoadCallbackList = [];
                    this.layoutIsBeingLoaded = false;
                }, this);
            }.bind(this));
        },

        _convertLayout: function (listLayout, model) {
            model = model || this.collection.model.prototype;

            var layout = {
                rows: [],
                right: false,
            };

            for (var i in listLayout.rows) {
                var row = listLayout.rows[i];
                var layoutRow = [];
                for (var j in row) {

                    var e = row[j];
                    var type = e.type || model.getFieldType(e.name) || 'base';

                    var item = {
                        name: e.name + 'Field',
                        field: e.name,
                        view: e.view || model.getFieldParam(e.name, 'view') || this.getFieldManager().getViewName(type),
                        options: {
                            defs: {
                                name: e.name,
                                params: e.params || {}
                            },
                            mode: 'list'
                        }
                    };
                    if (e.link) {
                        item.options.mode = 'listLink';
                    }
                    layoutRow.push(item);
                }
                layout.rows.push(layoutRow);
            }

            if ('right' in listLayout) {
                if (listLayout.right != false) {
                    var name = listLayout.right.name || 'right';
                    layout.right = {
                        field: name,
                        name: name,
                        view: listLayout.right.view,
                        options: {
                            defs: {
                                params: {
                                    width: listLayout.right.width || '7%'
                                }
                            }
                        }
                    };
                }
            } else {
                if (this.rowActionsView) {
                    layout.right = this.getRowActionsDefs();
                }
            }
            return layout;
        },

        getRowSelector: function (id) {
            return 'li[data-id="' + id + '"]';
        },

        getItemEl: function (model, item) {
            return this.options.el + ' li[data-id="' + model.id + '"] .cell[data-name="' + item.field + '"]';
        },

        getRowContainerHtml: function (id) {
            return '<li data-id="'+id+'" class="list-group-item list-row"></li>';
        },

        prepareInternalLayout: function (internalLayout, model) {
            var rows = internalLayout.rows || [];
            rows.forEach(function (row) {
                row.forEach(function (col) {
                    col.el = this.getItemEl(model, col);
                }, this);
            }, this);
            if (internalLayout.right) {
                internalLayout.right.el = this.getItemEl(model, internalLayout.right);
            }
        },

        fetchAttributeListFromLayout: function () {
            var list = [];
            if (this.listLayout.rows) {
                this.listLayout.rows.forEach(function (row) {
                    row.forEach(function (item) {
                        if (!item.name) return;
                        var field = item.name;
                        var fieldType = this.getMetadata().get(['entityDefs', this.scope, 'fields', field, 'type']);
                        if (!fieldType) return;
                        this.getFieldManager().getAttributeList(fieldType, field).forEach(function (attribute) {
                            list.push(attribute);
                        }, this);
                    }, this);
                }, this);
            }
            return list;
        }

    });
});
