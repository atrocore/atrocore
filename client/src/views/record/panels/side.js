

Espo.define('views/record/panels/side', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/panels/side',

        fieldList: null,

        data: function () {
            return {
                fieldList: this.getFieldList(),
                hiddenFields: this.recordHelper.getHiddenFields()
            };
        },

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
            }
        },

        mode: 'detail',

        actionList: null,

        buttonList: null,

        readOnly: false,

        inlineEditDisabled: false,

        disabled: false,

        init: function () {
            this.panelName = this.options.panelName;
            this.defs = this.options.defs || {};
            this.recordHelper = this.options.recordHelper;

            if ('disabled' in this.options) {
                this.disabled = this.options.disabled;
            }

            this.buttonList = _.clone(this.defs.buttonList || this.buttonList || []);
            this.actionList = _.clone(this.defs.actionList || this.actionList || []);

            this.fieldList = this.options.fieldList || this.fieldList || this.defs.fieldList || [];

            this.mode = this.options.mode || this.mode;

            this.readOnlyLocked = this.options.readOnlyLocked || this.readOnly;
            this.readOnly = this.readOnly || this.options.readOnly;
            this.inlineEditDisabled = this.inlineEditDisabled || this.options.inlineEditDisabled;

            this.recordViewObject = this.options.recordViewObject;
        },

        setup: function () {
            this.setupFields();

            this.fieldList = this.fieldList.map(function (d) {
                var item = d;
                if (typeof item !== 'object') {
                    item = {
                        name: item,
                        viewKey: item + 'Field'
                    }
                }
                item = Espo.Utils.clone(item);
                item.viewKey = item.name + 'Field';

                if (this.recordHelper.getFieldStateParam(item.name, 'hidden') !== null) {
                    item.hidden = this.recordHelper.getFieldStateParam(item.name, 'hidden');
                } else {
                    this.recordHelper.setFieldStateParam(item.name, item.hidden || false);
                }
                return item;
            }, this);

            this.fieldList = this.fieldList.filter(function (item) {
                if (!item.name) return;
                if (!(item.name in (((this.model.defs || {}).fields) || {}))) return;
                return true;
            }, this);

            this.createFields();
        },

        afterRender: function () {
            if (this.$el.children().size() === 0) {
                this.$el.parent().addClass('hidden');
            }
        },

        setupFields: function () {
        },

        createField: function (field, viewName, params, mode, readOnly, options) {
            var type = this.model.getFieldType(field) || 'base';
            viewName = viewName || this.model.getFieldParam(field, 'view') || this.getFieldManager().getViewName(type);

            var o = {
                model: this.model,
                el: this.options.el + ' .field[data-name="' + field + '"]',
                defs: {
                    name: field,
                    params: params || {},
                },
                mode: mode || this.mode
            };

            if (options) {
                for (var param in options) {
                    o[param] = options[param];
                }
            }

            var readOnlyLocked = this.readOnlyLocked;

            if (this.readOnly) {
                o.readOnly = true;
            } else {
                if (readOnly !== null) {
                    o.readOnly = readOnly
                }
                if (readOnly) {
                    readOnlyLocked = true;
                }
            }
            if (this.inlineEditDisabled) {
                o.inlineEditDisabled = true;
            }

            if (this.recordHelper.getFieldStateParam(field, 'hidden')) {
                o.disabled = true;
            }
            if (this.recordHelper.getFieldStateParam(field, 'hiddenLocked')) {
                o.disabledLocked = true;
            }
            if (this.recordHelper.getFieldStateParam(field, 'readOnly')) {
                o.readOnly = true;
            }
            if (this.recordHelper.getFieldStateParam(field, 'required') !== null) {
                o.defs.params.required = this.recordHelper.getFieldStateParam(field, 'required');
            }
            if (!readOnlyLocked && this.recordHelper.getFieldStateParam(field, 'readOnlyLocked')) {
                readOnlyLocked = true;
            }

            if (readOnlyLocked) {
                o.readOnlyLocked = readOnlyLocked;
            }

            if (this.recordHelper.hasFieldOptionList(field)) {
                o.customOptionList = this.recordHelper.getFieldOptionList(field);
            }

            var viewKey = field + 'Field';

            this.createView(viewKey, viewName, o);
        },

        createFields: function () {
            this.getFieldList().forEach(function (item) {
                var view = null;
                var field;
                var readOnly = null;
                if (typeof item === 'object') {
                    field = item.name;
                    view = item.view;
                    if ('readOnly' in item) {
                        readOnly = item.readOnly;
                    }
                } else {
                   field = item;
                }
                if (!(field in this.model.defs.fields)) {
                    return;
                }
                this.createField(field, view, null, null, readOnly);

            }, this);
        },

        getFields: function () {
            return this.getFields();
        },

        getFieldViews: function () {
            var fields = {};

            this.getFieldList().forEach(function (item) {
                if (this.hasView(item.viewKey)) {
                    fields[item.name] = this.getView(item.viewKey);
                }
            }, this);

            return fields;
        },

        getFieldList: function () {
            return this.fieldList.map(function (item) {
                if (typeof item !== 'object') {
                    return {
                        name: item
                    };
                }
                return item;
            }, this);
        },

        getActionList: function () {
            return this.actionList || [];
        },

        getButtonList: function () {
            return this.buttonList || [];
        },

        actionRefresh: function () {
            this.model.fetch();
        }

    });
});
