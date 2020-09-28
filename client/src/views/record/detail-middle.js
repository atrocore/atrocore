

Espo.define('views/record/detail-middle', 'view', function (Dep) {

    return Dep.extend({

        init: function () {
            this.recordHelper = this.options.recordHelper;
            this.scope = this.model.name;
        },

        data: function () {
            return {
                hiddenPanels: this.recordHelper.getHiddenPanels(),
                hiddenFields: this.recordHelper.getHiddenFields()
            };
        },

        showPanel: function (name) {
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="'+name+'"]').removeClass('hidden');
            }
            this.recordHelper.setPanelStateParam(name, 'hidden', false);
        },

        hidePanel: function (name) {
            if (this.isRendered()) {
                this.$el.find('.panel[data-name="'+name+'"]').addClass('hidden');
            }
            this.recordHelper.setPanelStateParam(name, 'hidden', true);
        },

        hideField: function (name) {
            this.recordHelper.setFieldStateParam(name, 'hidden', true);

            var processHtml = function () {
                var fieldView = this.getFieldView(name);

                if (fieldView) {
                    var $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.addClass('hidden');
                    $label.addClass('hidden');
                    $cell.addClass('hidden-cell');
                } else {
                    this.$el.find('.cell[data-name="' + name + '"]').addClass('hidden-cell');
                    this.$el.find('.field[data-name="' + name + '"]').addClass('hidden');
                    this.$el.find('label.control-label[data-name="' + name + '"]').addClass('hidden');
                }
            }.bind(this);
            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                view.setDisabled();
            }
        },

        showField: function (name) {
            if (this.recordHelper.getFieldStateParam(name, 'hiddenLocked')) {
                return;
            }
            this.recordHelper.setFieldStateParam(name, 'hidden', false);

            var processHtml = function () {
                var fieldView = this.getFieldView(name);

                if (fieldView) {
                    var $field = fieldView.$el;
                    var $cell = $field.closest('.cell[data-name="' + name + '"]');
                    var $label = $cell.find('label.control-label[data-name="' + name + '"]');

                    $field.removeClass('hidden');
                    $label.removeClass('hidden');
                    $cell.removeClass('hidden-cell');
                } else {
                    this.$el.find('.cell[data-name="' + name + '"]').removeClass('hidden-cell');
                    this.$el.find('.field[data-name="' + name + '"]').removeClass('hidden');
                    this.$el.find('label.control-label[data-name="' + name + '"]').removeClass('hidden');
                }
            }.bind(this);

            if (this.isRendered()) {
                processHtml();
            } else {
                this.once('after:render', function () {
                    processHtml();
                }, this);
            }

            var view = this.getFieldView(name);
            if (view) {
                if (!view.disabledLocked) {
                    view.setNotDisabled();
                }
            }
        },

        getFields: function () {
            return this.getFieldViews();
        },

        getFieldViews: function () {
            var nestedViews = this.nestedViews;
            var fieldViews = {};
            for (var viewKey in this.nestedViews) {
                var name = this.nestedViews[viewKey].name;
                fieldViews[name] = this.nestedViews[viewKey];
            }
            return fieldViews;
        },

        getFieldView: function (name) {
            return (this.getFieldViews() || {})[name];
        },

        // TODO remove in 5.4.0
        getView: function (name) {
            var view = Dep.prototype.getView.call(this, name);
            if (!view) {
                view = this.getFieldView(name);
            }
            return view;
        }

    });
});
