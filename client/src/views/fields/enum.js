/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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
 */

Espo.define('views/fields/enum', ['views/fields/base', 'lib!Selectize'], function (Dep) {

    return Dep.extend({

        type: 'enum',

        listTemplate: 'fields/enum/list',

        listLinkTemplate: 'fields/enum/list-link',

        detailTemplate: 'fields/enum/detail',

        editTemplate: 'fields/enum/edit',

        searchTemplate: 'fields/enum/search',

        translatedOptions: null,

        searchTypeList: ['anyOf', 'noneOf', 'isEmpty', 'isNotEmpty'],

        prohibitedEmptyValue: false,

        prohibitedScopes: ['Settings', 'EntityManager'],

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.translatedOptions = this.translatedOptions;
            var value = this.model.get(this.name);

            if (this.model.has(this.name + 'OptionId')) {
                data.value = value = this.model.get(this.name + 'OptionId');
            }

            if (
                value !== null
                &&
                value !== undefined
                &&
                value !== ''
                ||
                value === '' && (value in (this.translatedOptions || {}) && (this.translatedOptions || {})[value] !== '')
            ) {
                data.isNotEmpty = true;
            }

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.params.options) {
                var methodName = 'get' + Espo.Utils.upperCaseFirst(this.name) + 'Options';
                if (typeof this.model[methodName] == 'function') {
                    this.params.options = this.model[methodName].call(this.model);
                }
            }

            // prepare default
            if (this.mode === 'edit' && this.model.isNew() && this.params.default) {
                let optionsIds = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'optionsIds']);
                if (optionsIds) {
                    let index = optionsIds.indexOf(this.params.default);
                    // set current value to default value only if current value is invalid
                    if (this.params.options[index] && !this.params.options.includes(this.model.get(this.name))) {
                        this.params.default = this.params.options[index];
                        this.model.set(this.name, this.params.options[index]);
                    }
                }
            }

            this.setupOptions();

            if ('translatedOptions' in this.options) {
                this.translatedOptions = this.options.translatedOptions;
            }

            if ('translatedOptions' in this.params) {
                this.translatedOptions = this.params.translatedOptions;
            }

            if (this.translatedOptions === null && this.model.defs.fields[this.name] && this.model.defs.fields[this.name].translatedOptions) {
                this.translatedOptions = Espo.Utils.clone(this.model.defs.fields[this.name].translatedOptions);
            }

            this.setupTranslation();

            if (this.translatedOptions === null) {
                let translatedOptions = {};
                let hasOptionTranslate = false;
                (this.params.options || []).map(item => {
                    translatedOptions[item] = this.getLanguage().translateOption(item, this.name, this.model.name);
                    if (translatedOptions[item] !== item) {
                        hasOptionTranslate = true;
                    }
                });
                if (hasOptionTranslate) {
                    this.translatedOptions = translatedOptions;
                }
            }

            if (this.translatedOptions === null) {
                this.translatedOptions = {};
                (this.params.options || []).map(function (item) {
                    this.translatedOptions[item] = this.getLanguage().translate(item, 'labels', this.model.name) || item;
                }.bind(this));
            }

            if (this.params.isSorted && this.translatedOptions) {
                this.params.options = Espo.Utils.clone(this.params.options);
                this.params.options = this.params.options.sort(function (v1, v2) {
                    return (this.translatedOptions[v1] || v1).localeCompare(this.translatedOptions[v2] || v2);
                }.bind(this));
            }

            if (this.options.customOptionList) {
                this.setOptionList(this.options.customOptionList);
            }

            this.prohibitedEmptyValue = this.prohibitedEmptyValue || this.options.prohibitedEmptyValue || this.model.getFieldParam(this.name, 'prohibitedEmptyValue');

            if (!this.prohibitedEmptyValue) {
                const scopeIsAllowed = !this.prohibitedScopes.includes(this.model.name);
                const isArray = Array.isArray((this.params || {}).options);

                if (isArray && scopeIsAllowed && !this.params.options.includes('')) {
                    this.params.options.unshift('');
                    if (this.params.optionColors && this.params.optionColors.length > 0) {
                        this.params.optionColors.unshift('');
                    }

                    if (Espo.Utils.isObject(this.translatedOptions)) {
                        this.translatedOptions[''] = '';
                    }

                    if (this.model.isNew() && this.mode === 'edit' && !this.model.get('_duplicatingEntityId') && !this.params.default) {
                        this.model.set({[this.name]: ''});
                    }
                }
            }

            if (this.options.disabledOptionList) {
                this.disableOptions(this.options.disabledOptionList)
            }
        },

        setupTranslation: function () {
            if (this.params.translation) {
                var translationObj;
                var data = this.getLanguage().data;
                var arr = this.params.translation.split('.');
                var pointer = this.getLanguage().data;
                arr.forEach(function (key) {
                    if (key in pointer) {
                        pointer = pointer[key];
                        translationObj = pointer;
                    }
                }, this);

                this.translatedOptions = null;
                var translatedOptions = {};
                if (this.params.options) {
                    this.params.options.forEach(function (item) {
                        if (typeof translationObj === 'object' && item in translationObj) {
                            translatedOptions[item] = translationObj[item];
                        } else {
                            translatedOptions[item] = item;
                        }
                    }, this);
                    var value = this.model.get(this.name);
                    if ((value || value === '') && !(value in translatedOptions)) {
                        if (typeof translationObj === 'object' && value in translationObj) {
                            translatedOptions[value] = translationObj[value];
                        }
                    }
                    this.translatedOptions = translatedOptions;
                }
            }
        },

        setupOptions: function () {
            if (this.params.options) {
                let options = [];
                this.params.options.forEach(option => {
                    if (option.value) {
                        options.push(option.value);
                    } else {
                        options.push(option);
                    }
                });
                this.params.options = options;
            }
        },

        disableOptions: function (disabledOptionList) {
            if (!this.originalOptionList) {
                this.originalOptionList = this.params.options;
            }

            const options = []
            this.originalOptionList.forEach(option => {
                if (disabledOptionList.includes(option)) {
                    return
                }
                options.push(option)
            })

            this.setOptionList(options)
        },

        setOptionList: function (optionList) {
            if (!this.originalOptionList) {
                this.originalOptionList = this.params.options;
            }
            this.params.options = Espo.Utils.clone(optionList);

            if (this.mode == 'edit') {
                if (this.isRendered()) {
                    this.reRender();
                    if (!(this.params.options || []).includes(this.model.get(this.name))) {
                        this.trigger('change');
                    }
                } else {
                    this.once('after:render', function () {
                        if (!(this.params.options || []).includes(this.model.get(this.name))) {
                            this.trigger('change');
                        }
                    }, this);
                }
            }
        },

        resetOptionList: function () {
            if (this.originalOptionList) {
                this.params.options = Espo.Utils.clone(this.originalOptionList);
            }

            if (this.mode == 'edit') {
                if (this.isRendered()) {
                    this.reRender();
                }
            }
        },

        setupSearch: function () {
            this.events = _.extend({
                'change select.search-type': function (e) {
                    this.handleSearchType($(e.currentTarget).val());
                },
            }, this.events || {});
        },

        handleSearchType: function (type) {
            var $inputContainer = this.$el.find('div.input-container');

            if (~['anyOf', 'noneOf'].indexOf(type)) {
                $inputContainer.removeClass('hidden');
            } else {
                $inputContainer.addClass('hidden');
            }
        },

        updateLanguagesFields: function (model, value) {
            if (!this.getConfig().get('isMultilangActive')) {
                return;
            }

            // find key for selected option
            let key = null;
            (this.model.getFieldParam(this.name, 'options') || []).forEach((v, k) => {
                if (v === value) {
                    key = k;
                }
            });

            // collect fields that need to be updated
            let fields = [];
            let mainField = this.model.getFieldParam(this.name, 'multilangField') || this.name;
            if (mainField !== this.name) {
                fields.push(mainField);
            }
            $.each((this.getMetadata().get(`entityDefs.${this.model.urlRoot}.fields`) || {}), (field, defs) => {
                if (defs.multilangField && defs.multilangField === mainField && this.name !== field) {
                    fields.push(field);
                }
            });

            // update fields
            fields.forEach(field => {
                let options = this.model.getFieldParam(field, 'options') || this.model.getFieldParam(mainField, 'options');

                if (key && options[key]) {
                    this.model.set(field, options[key]);
                } else {
                    this.model.set(field, null);
                }
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:' + this.name, (model, value) => {
                    this.updateLanguagesFields(model, value);
                });
            }

            if (this.mode == 'search') {

                var $element = this.$element = this.$el.find('[name="' + this.name + '"]');

                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);

                var valueList = this.getSearchParamsData().valueList || this.searchParams.value || [];
                this.$element.val(valueList.join(':,:'));

                var data = [];
                (this.params.options || []).forEach(function (value) {
                    var label = this.getLanguage().translateOption(value, this.name, this.scope);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[value];
                        }
                    }
                    data.push({
                        value: value,
                        label: label
                    });
                }, this);

                this.$element.selectize({
                    options: data,
                    delimiter: ':,:',
                    labelField: 'label',
                    valueField: 'value',
                    highlight: false,
                    searchField: ['label'],
                    plugins: ['remove_button'],
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
                });

                this.$el.find('.selectize-dropdown-content').addClass('small');
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.name)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        fetch: function () {
            var value = this.$el.find('[name="' + this.name + '"]').val();
            if (value) {
                value = value.replace(/~dbq~/g, '"');
            }
            var data = {};
            data[this.name] = value;
            return data;
        },

        parseItemForSearch: function (item) {
            return item;
        },

        clearSearch: function () {
            Dep.prototype.clearSearch.call(this);

            this.$element.get(0).selectize.clear();
        },

        fetchSearch: function () {
            var type = this.$el.find('[name="' + this.name + '-type"]').val();

            var list = this.$element.val().split(':,:');
            if (list.length === 1 && list[0] == '') {
                list = [];
            }

            list.forEach(function (item, i) {
                list[i] = this.parseItemForSearch(item);
            }, this);

            if (type === 'anyOf') {
                if (list.length === 0) {
                    return {
                        data: {
                            type: 'anyOf',
                            valueList: list
                        }
                    };
                }
                return {
                    type: 'in',
                    value: list,
                    data: {
                        type: 'anyOf',
                        valueList: list
                    }
                };
            } else if (type === 'noneOf') {
                if (list.length === 0) {
                    return {
                        data: {
                            type: 'noneOf',
                            valueList: list
                        }
                    };
                }
                return {
                    type: 'or',
                    value: [
                        {
                            type: 'isNull',
                            attribute: this.name
                        },
                        {
                            type: 'notIn',
                            value: list,
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: 'noneOf',
                        valueList: list
                    }
                };
            } else if (type === 'isEmpty') {
                return {
                    type: 'or',
                    value: [
                        {
                            type: 'isNull',
                            attribute: this.name
                        },
                        {
                            type: 'equals',
                            value: '',
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: 'isEmpty'
                    }
                };
            } else if (type === 'isNotEmpty') {
                return {
                    type: 'and',
                    value: [
                        {
                            type: 'isNotNull',
                            attribute: this.name
                        },
                        {
                            type: 'notEquals',
                            value: '',
                            attribute: this.name
                        }
                    ],
                    data: {
                        type: 'isNotEmpty'
                    }
                };
            }
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || 'anyOf';
        },

        createQueryBuilderFilter() {
            const scope = this.model.urlRoot;

            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', scope),
                type: 'string',
                operators: [
                    'in',
                    'not_in',
                    'is_null',
                    'is_not_null'
                ],
                input: (rule, inputName) => {
                    if (!rule || !inputName) {
                        return '';
                    }
                    this.filterValue = null;
                    this.getModelFactory().create(null, model => {
                        this.createView(inputName, 'views/fields/multi-enum', {
                            name: 'value',
                            el: `#${rule.id} .field-container`,
                            model: model,
                            mode: 'edit',
                            defs: {
                                name: 'value',
                                params: {
                                    required: true,
                                    optionsIds: this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'optionsIds']) || [],
                                    options: this.getMetadata().get(['entityDefs', scope, 'fields', this.name, 'options']) || []
                                }
                            },
                        }, view => {
                            this.listenTo(view, 'change', () => {
                                this.filterValue = model.get('value');
                                rule.$el.find(`input[name="${inputName}"]`).trigger('change');
                            });
                            this.renderAfterEl(view, `#${rule.id} .field-container`);
                        });
                        this.listenTo(this.model, 'afterInitQueryBuilder', () => {
                            model.set('value', rule.value);
                        });
                    });
                    return `<div class="field-container"></div><input type="hidden" name="${inputName}" />`;
                },
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});

