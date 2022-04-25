/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 *
 * This software is not allowed to be used in Russia and Belarus.
 */

Espo.define('views/fields/multi-enum', ['views/fields/array', 'lib!Selectize'], function (Dep, Selectize) {

    return Dep.extend({

        type: 'multiEnum',

        listTemplate: 'fields//array/list',

        detailTemplate: 'fields/array/detail',

        editTemplate: 'fields/multi-enum/edit',

        dragDrop: true,

        events: {
        },

        data: function () {
            return _.extend({
                optionList: this.params.options || []
            }, Dep.prototype.data.call(this));
        },

        getTranslatedOptions: function () {
            return (this.params.options || []).map(function (item) {
                if (this.translatedOptions != null) {
                    if (item in this.translatedOptions) {
                        return this.translatedOptions[item];
                    }
                }
                return item;
            });
        },

        setup: function () {
            if (this.options.dragDrop === false || this.model.getFieldParam(this.name, 'dragDrop') === false) {
                this.dragDrop = false;
            }

            Dep.prototype.setup.call(this);
        },

        afterRender: function () {
            if (this.mode === 'edit') {
                if (this.options.defs && this.options.defs.params && this.options.defs.params.isMultilang) {
                    this.listenTo(this.model, 'change:' + this.name, function (model, value) {
                        this.updateLocaleFields(model, value);
                    }.bind(this));
                }

                this.$element = this.$el.find('[name="' + this.name + '"]');

                var data = [];

                var valueList = Espo.Utils.clone(this.selected);
                for (var i in valueList) {
                    var value = valueList[i];
                    if (valueList[i] === '') {
                        valueList[i] = '__emptystring__';
                    }
                    if (!~(this.params.options || []).indexOf(value)) {
                        data.push({
                            value: value,
                            label: value
                        });
                    }
                }
                valueList = valueList.map(item => item.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-'));
                this.$element.val(valueList.join(':,:'));

                (this.params.options || []).forEach(function (value) {
                    var label = this.getLanguage().translateOption(value, this.name, this.scope);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[value];
                        }
                    }
                    if (value === '') {
                        value = '__emptystring__';
                    }
                    if (label === '') {
                        label = this.translate('None');
                    }
                    data.push({
                        value: value,
                        label: label
                    });
                }, this);

                data.forEach(item => item.value = item.value.replace(/"/g, '-quote-').replace(/\\/g, '-backslash-'));

                let plugins = ['remove_button'];
                if (this.dragDrop) {
                    plugins.push('drag_drop');
                }

                var selectizeOptions = {
                    options: data,
                    delimiter: ':,:',
                    labelField: 'label',
                    valueField: 'value',
                    highlight: false,
                    searchField: ['label'],
                    placeholder: this.translate('click to add...'),
                    plugins: plugins,
                    score: function (search) {
                        var score = this.getScoreFunction(search);
                        search = search.toLowerCase();
                        return function (item) {
                            if (item.label.toLowerCase().indexOf(search) === 0) {
                                return score(item);
                            }
                            return 0;
                        };
                    }
                };

                if (!(this.params.options || []).length) {
                    selectizeOptions.persist = false;
                    selectizeOptions.create = function (input) {
                        return {
                            value: input,
                            label: input
                        }
                    };
                    selectizeOptions.render = {
                        option_create: function (data, escape) {
                            return '<div class="create"><strong>' + escape(data.input) + '</strong>&hellip;</div>';
                        }
                    };
                }

                this.$element.selectize(selectizeOptions);

                if (this.$element.size()) {
                    let depPositionDropdown = this.$element[0].selectize.positionDropdown;
                    this.$element[0].selectize.positionDropdown = function () {
                        depPositionDropdown.call(this);

                        this.$dropdown.hide();
                        let pageHeight = $(document).height();
                        this.$dropdown.show();
                        let dropdownHeight = this.$dropdown.outerHeight(true);
                        if (this.$dropdown.offset().top + dropdownHeight > pageHeight) {
                            this.$dropdown.css({
                                'top': `-${dropdownHeight}px`
                            });
                        }
                    };
                }

                this.$element.on('change', function () {
                    this.trigger('change');
                }.bind(this));
            }

            if (this.mode == 'search') {
                this.renderSearch();
            }
        },

        fetch: function () {
            let value = (this.$element.length === 0) ? '' : this.$element.val();
            var list = value.split(':,:');
            if (list.length == 1 && list[0] == '') {
                list = [];
            }
            for (var i in list) {
                if (list[i] === '__emptystring__') {
                    list[i] = '';
                }
            }
            var data = {};
            data[this.name] = list.map(item => item.replace(/-quote-/g, '"').replace(/-backslash-/g, '\\'));

            return data;
        },

        validateRequired: function () {
            if (this.isRequired()) {
                var value = this.model.get(this.name);
                if (!value || value.length == 0) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '.selectize-control');
                    return true;
                }
            }
        },

        updateLocaleFields: function (model, value) {
            if (!this.getConfig().get('isMultilangActive')) {
                return;
            }

            let keys = [];
            if (value) {
                value.forEach(function (item) {
                    (this.options.defs.params.options || []).forEach(function (v, k) {
                        if (v === item) {
                            keys.push(k)
                        }
                    }, this);
                }, this);
            }

            let locales = this.getConfig().get('inputLanguageList') || [];
            locales.forEach(function (v, k) {
                let localeField = this.name + v.charAt(0).toUpperCase() + v.charAt(1) + v.charAt(3) + v.charAt(4).toLowerCase();
                let localeFieldOptions = this.model.getFieldParam(localeField, 'options');

                let localeValue = [];
                if (localeFieldOptions) {
                    keys.forEach(function (key) {
                        localeValue.push(typeof localeFieldOptions[key] === 'undefined' ? null : localeFieldOptions[key]);
                    });
                }

                this.model.set(localeField, localeValue);
            }, this);
        },

    });
});


