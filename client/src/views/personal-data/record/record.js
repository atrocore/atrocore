

Espo.define('views/personal-data/record/record', 'views/record/base', function (Dep) {

    return Dep.extend({

        template: 'personal-data/record/record',

        events: _.extend({
            'click .checkbox': function (e) {
                var name = $(e.currentTarget).data('name');
                if (e.currentTarget.checked) {
                    if (!~this.checkedFieldList.indexOf(name)) {
                        this.checkedFieldList.push(name);
                    }

                    if (this.checkedFieldList.length == this.fieldList.length) {
                        this.$el.find('.checkbox-all').prop('checked', true);
                    } else {
                        this.$el.find('.checkbox-all').prop('checked', false);
                    }
                } else {
                    var index = this.checkedFieldList.indexOf(name);
                    if (~index) {
                        this.checkedFieldList.splice(index, 1);
                    }

                    this.$el.find('.checkbox-all').prop('checked', false);
                }

                this.trigger('check', this.checkedFieldList);
            },

            'click .checkbox-all': function (e) {
                if (e.currentTarget.checked) {
                    this.checkedFieldList = Espo.Utils.clone(this.fieldList);

                    this.$el.find('.checkbox').prop('checked', true);
                } else {
                    this.checkedFieldList = [];

                    this.$el.find('.checkbox').prop('checked', false);
                }

                this.trigger('check', this.checkedFieldList);
            },
        }, Dep.prototype.events),

        data: function () {
            var data = {};
            data.fieldDataList = this.getFieldDataList();
            data.scope = this.scope;
            data.editAccess = this.editAccess;
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.scope = this.model.name;

            this.fieldList = [];

            this.checkedFieldList = [];

            this.editAccess = this.getAcl().check(this.model, 'edit');

            var fieldDefs = this.getMetadata().get(['entityDefs', this.scope, 'fields']) || {};

            var fieldList = [];

            for (var field in fieldDefs) {
                var defs = fieldDefs[field];
                if (defs.isPersonalData) {
                    fieldList.push(field);
                }
            }

            fieldList.forEach(function (field) {
                var type = fieldDefs[field].type;
                var attributeList = this.getFieldManager().getActualAttributeList(type, field);

                var isNotEmpty = false;
                attributeList.forEach(function (attribute) {
                    var value = this.model.get(attribute);
                    if (value) {
                        if (Object.prototype.toString.call(value) === '[object Array]') {
                            if (value.length) {
                                return;
                            }
                        }
                        isNotEmpty = true;
                    }
                }, this);

                var hasAccess = !~this.getAcl().getScopeForbiddenFieldList(this.scope, 'view').indexOf(field);

                if (isNotEmpty && hasAccess) {
                    this.fieldList.push(field);
                }
            }, this);

            this.fieldList = this.fieldList.sort(function (v1, v2) {
                return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));

            this.fieldList.forEach(function (field) {
                this.createField(field, null, null, 'detail', true);
            }, this);
        },

        getFieldDataList: function () {
            var forbiddenList = this.getAcl().getScopeForbiddenFieldList(this.scope, 'edit');

            var list = [];
            this.fieldList.forEach(function (field) {
                list.push({
                    name: field,
                    key: field + 'Field',
                    editAccess: this.editAccess && !~forbiddenList.indexOf(field)
                });
            }, this);
            return list;
        }

    });
});
