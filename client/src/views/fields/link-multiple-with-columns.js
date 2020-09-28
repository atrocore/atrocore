

Espo.define('views/fields/link-multiple-with-columns', 'views/fields/link-multiple', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var columnsDefsInitial = this.columnsDefs || {};

            this.columnsDefs = {};

            this.columnsName = this.name + 'Columns';
            this.columns = Espo.Utils.cloneDeep(this.model.get(this.columnsName) || {});

            this.listenTo(this.model, 'change:' + this.columnsName, function () {
                this.columns = Espo.Utils.cloneDeep(this.model.get(this.columnsName) || {});
            }, this);

            var columns = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'columns']) || {};
            var columnList = Object.keys(columns);

            this.columnList = this.columnList || columnList;

            this.columnList.forEach(function (column) {
                if (column in columnsDefsInitial) {
                    this.columnsDefs[column] = Espo.Utils.cloneDeep(columnsDefsInitial[column]);
                    return;
                }
                if (column in columns) {
                    var field = columns[column];

                    var o = {};
                    o.field = field;

                    o.scope = this.foreignScope;
                    if (
                        !this.getMetadata().get(['entityDefs', this.foreignScope, 'fields', field, 'type'])
                        &&
                        this.getMetadata().get(['entityDefs', this.model.name, 'fields', field, 'type'])
                    ) {
                        o.scope = this.model.name;
                    }

                    var fieldDefs = this.getMetadata().get(['entityDefs', o.scope, 'fields', field]) || {};

                    o.type = fieldDefs.type;
                    if (o.type === 'enum') {
                        o.options = fieldDefs.options;
                    }
                    if ('default' in fieldDefs) {
                        o.default = fieldDefs.default;
                    }
                    if ('maxLength' in fieldDefs) {
                        o.maxLength = fieldDefs.maxLength;
                    }
                    this.columnsDefs[column] = o;
                }
            }, this);

            if (this.mode === 'edit' || this.mode === 'detail') {
                this.events['click a[data-action="toggleBoolColumn"]'] = function (e) {
                    var id = $(e.currentTarget).data('id');
                    var column = $(e.currentTarget).data('column');
                    this.toggleBoolColumn(id, column);
                }
            }
        },

        toggleBoolColumn: function (id, column) {
            this.columns[id][column] = !this.columns[id][column];
            this.reRender();
        },

        getAttributeList: function () {
            var list = Dep.prototype.getAttributeList.call(this);
            list.push(this.name + 'Columns');
            return list;
        },

        getDetailLinkHtml: function (id, name) {
            name = name || this.nameHash[id];

            var roleHtml = '';

            this.columnList.forEach(function (column) {
                var value = (this.columns[id] || {})[column] || '';
                var columnHtml = '';

                var type = this.columnsDefs[column].type;
                if (value !== '' && value) {
                    if (type === 'enum') {
                        roleHtml += '<span class="text-muted small"> &#187; ' +
                            this.getHelper().stripTags(this.getLanguage().translateOption(value, this.columnsDefs[column].field, this.columnsDefs[column].scope)) +
                        '</span>';
                    } else if (type === 'varchar') {
                        roleHtml += '<span class="text-muted small"> &#187; ' +
                            value
                        '</span>';
                    }
                }
            }, this);

            var iconHtml = '';
            if (this.mode == 'detail') {
                iconHtml = this.getIconHtml();
            }

            var lineHtml = '<div>' + iconHtml + '<a href="#' + this.foreignScope + '/view/' + id + '">' + this.getHelper().stripTags(name) + '</a> ' + roleHtml + '</div>';
            return lineHtml;
        },

        getValueForDisplay: function () {
            if (this.mode == 'detail' || this.mode == 'list') {
                var names = [];
                this.ids.forEach(function (id) {
                    var lineHtml = this.getDetailLinkHtml(id);

                    names.push(lineHtml);
                }, this);
                return names.join('');
            }
        },

        deleteLink: function (id) {
            this.deleteLinkHtml(id);

            var index = this.ids.indexOf(id);
            if (index > -1) {
                this.ids.splice(index, 1);
            }
            delete this.nameHash[id];
            delete this.columns[id];
            this.afterDeleteLink(id);
            this.trigger('change');
        },

        getColumnValue: function (id, column) {
            return (this.columns[id] || {})[column];
        },

        addLink: function (id, name) {
            if (!~this.ids.indexOf(id)) {
                this.ids.push(id);
                this.nameHash[id] = name;
                this.columns[id] = {};

                this.columnList.forEach(function (column) {
                    this.columns[id][column] = null;
                    if ('default' in this.columnsDefs[column]) {
                        this.columns[id][column] = this.columnsDefs[column].default;
                    }
                }, this);

                this.afterAddLink(id);

                this.addLinkHtml(id, name);
            }
            this.trigger('change');
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        getJQSelect: function (column, id, value) {
            var $column = $('<select class="role form-control input-sm pull-right" data-id="'+id+'" data-column="'+column+'">');

            this.columnsDefs[column].options.forEach(function (item) {
                var selectedHtml = (item == value) ? 'selected': '';
                option = '<option value="'+item+'" '+selectedHtml+'>' + this.getLanguage().translateOption(item, this.columnsDefs[column].field, this.columnsDefs[column].scope) + '</option>';
                $column.append(option);
            }, this);

            return $column;
        },

        addLinkHtml: function (id, name) {
            if (this.mode == 'search') {
                return Dep.prototype.addLinkHtml.call(this, id, name);
            }
            var $container = this.$el.find('.link-container');
            var $el = $('<div class="form-inline list-group-item link-with-role link-group-item-with-columns clearfix">').addClass('link-' + id);

            var nameHtml = '<div class="link-item-name">' + this.getHelper().stripTags(name) + '&nbsp;' + '</div>';
            var removeHtml = '<a href="javascript:" class="pull-right" data-id="' + id + '" data-action="clearLink"><span class="fas fa-times"></a>';

            var columnFormElementJQList = [];
            var columnMenuItemJQList = [];
            this.columnList.forEach(function (column) {
                var value = (this.columns[id] || {})[column];
                var escapedValue = Handlebars.Utils.escapeExpression(value);

                var type = this.columnsDefs[column].type;
                var field = this.columnsDefs[column].field;
                var scope = this.columnsDefs[column].scope;

                var $column;

                if (type == 'enum') {
                    $column = this.getJQSelect(column, id, escapedValue);
                    columnFormElementJQList.push($column);
                } else if (type == 'varchar') {
                    var label = this.translate(field, 'fields', scope);
                    $column = $('<input class="role form-control input-sm pull-right" data-column="'+column+'" placeholder="'+label+'" data-id="'+id+'" value="' + (escapedValue || '') + '">');
                    if ('maxLength' in this.columnsDefs[column]) {
                        $column.attr('maxLength', this.columnsDefs[column].maxLength);
                    }
                    columnFormElementJQList.push($column);
                } else if (type == 'bool') {
                    var label = this.translate(field, 'fields', scope);
                    var $menuItem = $('<li>').append(
                        $('<a href="javascript:" data-action="toggleBoolColumn">').attr('data-column', column).attr('data-id', id).append(
                            $('<span class="check-icon fas fa-check fa-sm pull-right">').addClass(!value ? 'hidden' : '')
                        ).append(
                            $('<div>').text(label)
                        )
                    );
                    columnMenuItemJQList.push($menuItem);
                }
            }, this);

            $left = $('<div>');
            if (columnFormElementJQList.length === 1) {
                $left.append(columnFormElementJQList[0]);
            } else {
                columnFormElementJQList.forEach(function ($input) {
                    $left.append($input);
                }, this);
            }
            if (columnMenuItemJQList.length) {
                var $ul = $('<ul class="dropdown-menu">');
                columnMenuItemJQList.forEach(function ($item) {
                    $ul.append($item);
                }, this);
                $left.append(
                    $('<div class="btn-group pull-right">').append(
                        $('<button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">').append(
                            '<span class="caret">'
                        )
                    ).append($ul)
                );
            }
            $left.append(nameHtml);
            $el.append($left);


            $right = $('<div>');

            $right.append(removeHtml);
            $el.append($right);

            $container.append($el);

            if (this.mode == 'edit') {
                columnFormElementJQList.forEach(function ($column) {
                    var fetch = function ($target) {
                        if (!$target || !$target.size()) return;
                        var column = $target.data('column');
                        var value = $target.val().toString().trim();
                        var id = $target.data('id');
                        this.columns[id] = this.columns[id] || {};
                        this.columns[id][column] = value;
                    }.bind(this);
                    $column.on('change', function (e) {
                        var $target = $(e.currentTarget);
                        fetch($target);
                        this.trigger('change');
                    }.bind(this));
                    fetch($column);
                }, this);
            }
            return $el;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);
            data[this.columnsName] = Espo.Utils.cloneDeep(this.columns);
            return data;
        },

    });
});


