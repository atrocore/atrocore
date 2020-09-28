

Espo.define('views/fields/range-int', ['views/fields/base', 'views/fields/int'], function (Dep, Int) {

    return Dep.extend({

        type: 'rangeInt',

        listTemplate: 'fields/range-int/detail',

        detailTemplate: 'fields/range-int/detail',

        editTemplate: 'fields/range-int/edit',

        validations: ['required', 'int', 'range', 'order'],

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.ucName = Espo.Utils.upperCaseFirst(this.name);
            data.fromValue = this.model.get(this.fromField);
            data.toValue = this.model.get(this.toField);
            return data;
        },

        init: function () {
            var ucName = Espo.Utils.upperCaseFirst(this.options.defs.name);
            this.fromField = 'from' + ucName;
            this.toField = 'to' + ucName;
            Dep.prototype.init.call(this);
        },

        getValueForDisplay: function () {
            var fromValue = this.model.get(this.fromField);
            var toValue = this.model.get(this.toField);

            var fromValue = isNaN(fromValue) ? null : fromValue;
            var toValue = isNaN(toValue) ? null : toValue;

            if (fromValue !== null && toValue !== null) {
                return this.formatNumber(fromValue) + ' &#8211 ' + this.formatNumber(toValue);
            } else if (fromValue) {
                return '&#62;&#61; ' + this.formatNumber(fromValue);
            } else if (toValue) {
                return '&#60;&#61; ' + this.formatNumber(toValue);
            } else {
                return this.translate('None');
            }
        },

        setup: function () {
            if (this.getPreferences().has('decimalMark')) {
                this.decimalMark = this.getPreferences().get('decimalMark');
            } else {
                if (this.getConfig().has('decimalMark')) {
                    this.decimalMark = this.getConfig().get('decimalMark');
                }
            }
            if (this.getPreferences().has('thousandSeparator')) {
                this.thousandSeparator = this.getPreferences().get('thousandSeparator');
            } else {
                if (this.getConfig().has('thousandSeparator')) {
                    this.thousandSeparator = this.getConfig().get('thousandSeparator');
                }
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode == 'edit') {
                this.$from = this.$el.find('[name="' + this.fromField + '"]');
                this.$to = this.$el.find('[name="' + this.toField + '"]');

                this.$from.on('change', function () {
                    this.trigger('change');
                }.bind(this));
                this.$to.on('change', function () {
                    this.trigger('change');
                }.bind(this));
            }
        },

        validateRequired: function () {
            var validate = function (name) {
                if (this.model.isRequired(name)) {
                    if (this.model.get(name) === null) {
                        var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                        this.showValidationMessage(msg, '[name="'+name+'"]');
                        return true;
                    }
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateInt: function () {
            var validate = function (name) {
                if (isNaN(this.model.get(name))) {
                    var msg = this.translate('fieldShouldBeInt', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '[name="'+name+'"]');
                    return true;
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateRange: function () {
            var validate = function (name) {
                var value = this.model.get(name);

                if (value === null) {
                    return false;
                }

                var minValue = this.model.getFieldParam(name, 'min');
                var maxValue = this.model.getFieldParam(name, 'max');

                if (minValue !== null && maxValue !== null) {
                    if (value < minValue || value > maxValue ) {
                        var msg = this.translate('fieldShouldBeBetween', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                                                                                    .replace('{min}', minValue)
                                                                                    .replace('{max}', maxValue);
                        this.showValidationMessage(msg, '[name="'+name+'"]');
                        return true;
                    }
                } else {
                    if (minValue !== null) {
                        if (value < minValue) {
                            var msg = this.translate('fieldShouldBeLess', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                                                                                     .replace('{value}', minValue);
                            this.showValidationMessage(msg, '[name="'+name+'"]');
                            return true;
                        }
                    } else if (maxValue !== null) {
                        if (value > maxValue) {
                            var msg = this.translate('fieldShouldBeGreater', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name))
                                                                                        .replace('{value}', maxValue);
                            this.showValidationMessage(msg, '[name="'+name+'"]');
                            return true;
                        }
                    }
                }
            }.bind(this);

            var result = false;
            result = validate(this.fromField) || result;
            result = validate(this.toField) || result;
            return result;
        },

        validateOrder: function () {
            var fromValue = this.model.get(this.fromField);
            var toValue = this.model.get(this.toField);

            if (fromValue !== null && toValue !== null) {
                if (fromValue > toValue) {
                    var msg = this.translate('fieldShouldBeGreater', 'messages').replace('{field}', this.translate(this.toField, 'fields', this.model.name))
                                                                            .replace('{value}', this.translate(this.fromField, 'fields', this.model.name));

                    this.showValidationMessage(msg, '[name="'+this.fromField+'"]');
                    return true;
                }
            }
        },

        isRequired: function () {
            return this.model.getFieldParam(this.fromField, 'required') ||
                   this.model.getFieldParam(this.toField, 'required');
        },

        parse: function (value) {
            return Int.prototype.parse.call(this, value);
        },

        formatNumber: function (value) {
            return value;
            return Int.prototype.formatNumber.call(this, value);
        },

        fetch: function (form) {
            var data = {};
            data[this.fromField] = this.parse(this.$from.val().trim());
            data[this.toField] = this.parse(this.$to.val().trim());
            return data;
        }

    });
});

