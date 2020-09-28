

Espo.define('views/fields/address', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'address',

        listTemplate: 'fields/address/detail',

        detailTemplate: 'fields/address/detail',

        editTemplate: 'fields/address/edit',

        editTemplate1: 'fields/address/edit-1',

        editTemplate2: 'fields/address/edit-2',

        editTemplate3: 'fields/address/edit-3',

        editTemplate4: 'fields/address/edit-4',

        searchTemplate: 'fields/address/search',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.ucName = Espo.Utils.upperCaseFirst(this.name);

            this.addressPartList.forEach(function (item) {
                var value = this.model.get(this[item + 'Field']);
                data[item + 'Value'] = value;
            }, this);

            if (this.mode == 'detail' || this.mode == 'list') {
                data.formattedAddress = this.getFormattedAddress();
            }

            var isNotEmpty = false;

            return data;
        },

        setupSearch: function () {
            this.searchData.value = this.getSearchParamsData().value || this.searchParams.additionalValue;
        },

        getFormattedAddress: function () {
            var isNotEmpty = false;
            var isSet = false;
            this.addressAttributeList.forEach(function (attribute) {
                isNotEmpty = isNotEmpty || this.model.get(attribute);
                isSet = isSet || this.model.has(attribute);
            }, this);

            var isEmpty = !isNotEmpty;

            if (isEmpty) {
                if (this.mode === 'list') {
                    return '';
                }
                if (!isSet) {
                    return this.translate('...');
                }
                return this.translate('None');
            }

            var methodName = 'getFormattedAddress' + this.getAddressFormat().toString();

            if (methodName in this) {
                return this[methodName]();
            }
        },

        getFormattedAddress1: function () {
            var postalCodeValue = this.model.get(this.postalCodeField);
            var streetValue = this.model.get(this.streetField);
            var cityValue = this.model.get(this.cityField);
            var stateValue = this.model.get(this.stateField);
            var countryValue = this.model.get(this.countryField);

            var html = '';
            if (streetValue) {
                html += streetValue;
            }
            if (cityValue || stateValue || postalCodeValue) {
                if (html != '') {
                    html += '\n';
                }
                if (cityValue) {
                    html += cityValue;
                }
                if (stateValue) {
                    if (cityValue) {
                        html += ', ';
                    }
                    html += stateValue;
                }
                if (postalCodeValue) {
                    if (cityValue || stateValue) {
                        html += ' ';
                    }
                    html += postalCodeValue;
                }
            }
            if (countryValue) {
                if (html != '') {
                    html += '\n';
                }
                html += countryValue;
            }
            return html;
        },

        getFormattedAddress2: function () {
            var postalCodeValue = this.model.get(this.postalCodeField);
            var streetValue = this.model.get(this.streetField);
            var cityValue = this.model.get(this.cityField);
            var stateValue = this.model.get(this.stateField);
            var countryValue = this.model.get(this.countryField);

            var html = '';
            if (streetValue) {
                html += streetValue;
            }
            if (cityValue || postalCodeValue) {
                if (html != '') {
                    html += '\n';
                }
                if (postalCodeValue) {
                    html += postalCodeValue;
                    if (cityValue) {
                        html += ' ';
                    }
                }
                if (cityValue) {
                    html += cityValue;
                }
            }
            if (stateValue || countryValue) {
                if (html != '') {
                    html += '\n';
                }
                if (stateValue) {
                    html += stateValue;
                    if (countryValue) {
                        html += ' ';
                    }
                }
                if (countryValue) {
                    html += countryValue;
                }
            }
            return html;
        },

        getFormattedAddress3: function () {
            var postalCodeValue = this.model.get(this.postalCodeField);
            var streetValue = this.model.get(this.streetField);
            var cityValue = this.model.get(this.cityField);
            var stateValue = this.model.get(this.stateField);
            var countryValue = this.model.get(this.countryField);

            var html = '';
            if (countryValue) {
                html += countryValue;
            }
            if (cityValue || stateValue || postalCodeValue) {
                if (html != '') {
                    html += '\n';
                }
                if (postalCodeValue) {
                    html += postalCodeValue;
                }
                if (stateValue) {
                    if (postalCodeValue) {
                        html += ' ';
                    }
                    html += stateValue;
                }
                if (cityValue) {
                    if (postalCodeValue || stateValue) {
                        html += ' ';
                    }
                    html += cityValue;
                }
            }
            if (streetValue) {
                if (html != '') {
                    html += '\n';
                }
                html += streetValue;
            }
            return html;
        },

        getFormattedAddress4: function () {
            var postalCodeValue = this.model.get(this.postalCodeField);
            var streetValue = this.model.get(this.streetField);
            var cityValue = this.model.get(this.cityField);
            var stateValue = this.model.get(this.stateField);
            var countryValue = this.model.get(this.countryField);

            var html = '';
            if (streetValue) {
                html += streetValue;
            }
            if (cityValue) {
                if (html != '') {
                    html += '\n';
                }
                html += cityValue;
            }
            if (countryValue || stateValue || postalCodeValue) {
                if (html != '') {
                    html += '\n';
                }
                if (countryValue) {
                    html += countryValue;
                }
                if (stateValue) {
                    if (countryValue) {
                        html += ' - ';
                    }
                    html += stateValue;
                }
                if (postalCodeValue) {
                    if (countryValue || stateValue) {
                        html += ' ';
                    }
                    html += postalCodeValue;
                }
            }

            return html;
        },

        _getTemplateName: function () {
            if (this.mode == 'edit') {
                var prop = 'editTemplate' + this.getAddressFormat().toString();
                if (prop in this) {
                    return this[prop];
                }
            }
            return Dep.prototype._getTemplateName.call(this);
        },

        getAddressFormat: function () {
            return this.getConfig().get('addressFormat') || 1;
        },

        afterRender: function () {
            var self = this;

            if (this.mode == 'edit') {
                this.$street = this.$el.find('[name="' + this.streetField + '"]');
                this.$postalCode = this.$el.find('[name="' + this.postalCodeField + '"]');
                this.$state = this.$el.find('[name="' + this.stateField + '"]');
                this.$city = this.$el.find('[name="' + this.cityField + '"]');
                this.$country = this.$el.find('[name="' + this.countryField + '"]');

                this.$street.on('change', function () {
                    self.trigger('change');
                });
                this.$postalCode.on('change', function () {
                    self.trigger('change');
                });
                this.$state.on('change', function () {
                    self.trigger('change');
                });
                this.$city.on('change', function () {
                    self.trigger('change');
                });
                this.$country.on('change', function () {
                    self.trigger('change');
                });

                this.$street.on('input', function (e) {
                    var numberOfLines = e.currentTarget.value.split('\n').length;
                    var numberOfRows = this.$street.prop('rows');

                    if (numberOfRows < numberOfLines) {
                        this.$street.prop('rows', numberOfLines);
                    } else if (numberOfRows > numberOfLines) {
                        this.$street.prop('rows', numberOfLines);
                    }
                }.bind(this));

                var numberOfLines = this.$street.val().split('\n').length;
                this.$street.prop('rows', numberOfLines);
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var actualAttributePartList = this.getMetadata().get(['fields', this.type, 'actualFields']) || [];
            this.addressAttributeList = [];
            this.addressPartList = [];
            actualAttributePartList.forEach(function (item) {
                var attribute = this.name + Espo.Utils.upperCaseFirst(item);
                this.addressAttributeList.push(attribute);
                this.addressPartList.push(item);
                this[item + 'Field'] = attribute;
            }, this);
        },

        validateRequired: function () {
            var validate = function (name) {
                if (this.model.isRequired(name)) {
                    if (this.model.get(name) === '') {
                        var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate(name, 'fields', this.model.name));
                        this.showValidationMessage(msg, '[name="'+name+'"]');
                        return true;
                    }
                }
            }.bind(this);

            var result = false;
            result = validate(this.postalCodeField) || result;
            result = validate(this.streetField) || result;
            result = validate(this.stateField) || result;
            result = validate(this.cityField) || result;
            result = validate(this.countryField) || result;
            return result;
        },

        isRequired: function () {
            return this.model.getFieldParam(this.postalCodeField, 'required') ||
                   this.model.getFieldParam(this.streetField, 'required') ||
                   this.model.getFieldParam(this.stateField, 'required') ||
                   this.model.getFieldParam(this.cityField, 'required') ||
                   this.model.getFieldParam(this.countryField, 'required');
        },

        fetch: function () {
            var data = {};
            data[this.postalCodeField] = this.$postalCode.val().toString().trim();
            data[this.streetField] = this.$street.val().toString().trim();
            data[this.stateField] = this.$state.val().toString().trim();
            data[this.cityField] = this.$city.val().toString().trim();
            data[this.countryField] = this.$country.val().toString().trim();
            return data;
        },

        fetchSearch: function () {
            var value = this.$el.find('[name="'+this.name+'"]').val().toString().trim();
            if (value) {
                var data = {
                    type: 'or',
                    value: [
                        {
                            type: 'like',
                            field: this.postalCodeField,
                            value: value + '%'
                        },
                        {
                            type: 'like',
                            field: this.streetField,
                            value: value + '%'
                        },
                        {
                            type: 'like',
                            field: this.cityField,
                            value: value + '%'
                        },
                        {
                            type: 'like',
                            field: this.stateField,
                            value: value + '%'
                        },
                            {
                            type: 'like',
                            field: this.countryField,
                            value: value + '%'
                        }
                    ],
                    data: {
                        value: value
                    }
                };
                return data;
            }
            return false;
        }
    });
});
