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

Espo.define('views/fields/array', ['views/fields/base', 'lib!Selectize'], function (Dep) {

    return Dep.extend({

        type: 'array',

        listTemplate: 'fields/array/list',

        detailTemplate: 'fields/array/detail',

        editTemplate: 'fields/array/edit',

        searchTemplate: 'fields/array/search',

        searchTypeList: ['anyOf', 'noneOf', 'isEmpty', 'isNotEmpty'],

        maxItemLength: null,

        translatedOptions: null,

        data: function () {
            var itemHtmlList = [];
            (this.selected || []).forEach(function (value) {
                itemHtmlList.push(this.getItemHtml(value));
            }, this);

            return _.extend({
                selected: this.selected,
                translatedOptions: this.translatedOptions,
                hasOptions: !!this.params.options,
                itemHtmlList: itemHtmlList,
                isEmpty: (this.selected || []).length === 0,
                valueIsSet: this.model.has(this.name),
                maxItemLength: this.maxItemLength
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click [data-action="showAddModal"]': function () {
                var options = [];

                this.params.options.forEach(function (item) {
                    if (!~this.selected.indexOf(item)) {
                        options.push(item);
                    }
                }, this);
                this.createView('addModal', 'views/modals/array-field-add', {
                    options: options,
                    translatedOptions: this.translatedOptions
                }, function (view) {
                    view.render();
                    this.listenToOnce(view, 'add', function (item) {
                        this.addValue(item);
                        view.close();
                    }.bind(this));
                }.bind(this));
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.noEmptyString = this.params.noEmptyString;

            this.listenTo(this.model, 'change:' + this.name, function () {
                this.selected = Espo.Utils.clone(this.model.get(this.name)) || [];
                if (typeof this.selected === 'string') {
                    this.selected = JSON.parse(this.selected);
                }
            }, this);

            this.selected = Espo.Utils.clone(this.model.get(this.name) || []);

            if (this.model.has(this.name + 'OptionsIds')) {
                this.selected = Espo.Utils.clone(this.model.get(this.name + 'OptionsIds') || []);
            }

            if (Object.prototype.toString.call(this.selected) !== '[object Array]')    {
                this.selected = [];
            }

            if (typeof this.selected === 'string') {
                this.selected = JSON.parse(this.selected);
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

            if (this.translatedOptions === null) {
                this.setupTranslation();
            }

            if (this.options.customOptionList) {
                this.setOptionList(this.options.customOptionList, true);
            }
        },

        setupSearch: function () {
            this.events = _.extend({
                'change select.search-type': function (e) {
                    this.handleSearchType($(e.currentTarget).val());
                }
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

        setupTranslation: function () {
            var t = {};
            if (this.params.translation) {
                var data = this.getLanguage().data;
                var arr = this.params.translation.split('.');
                var pointer = this.getLanguage().data;
                arr.forEach(function (key) {
                    if (key in pointer) {
                        pointer = pointer[key];
                        t = pointer;
                    }
                }, this);
            } else {
                t = this.translate(this.name, 'options', this.model.name);
            }
            this.translatedOptions = null;
            var translatedOptions = {};
            if (this.params.options) {
                this.params.options.forEach(function (o) {
                    if (typeof t === 'object' && o in t) {
                        translatedOptions[o] = t[o];
                    } else {
                        translatedOptions[o] = o;
                    }
                }.bind(this));
                this.translatedOptions = translatedOptions;
            }
        },

        setupOptions: function () {

        },

        setOptionList: function (optionList, silent) {
            if (!this.originalOptionList) {
                this.originalOptionList = this.params.options;
            }
            this.params.options = Espo.Utils.clone(optionList);

            if (this.mode == 'edit' && !silent) {
                var selectedOptionList = [];
                this.selected.forEach(function (option) {
                    if (~optionList.indexOf(option)) {
                        selectedOptionList.push(option);
                    }
                }, this);
                this.selected = selectedOptionList;

                if (this.isRendered()) {
                    this.reRender();
                    this.trigger('change');
                } else {
                    this.once('after:render', function () {
                        this.trigger('change');
                    }, this);
                }
            }
        },

        setTranslatedOptions: function (translatedOptions) {
            this.translatedOptions = translatedOptions;
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

        afterRender: function () {
            if (this.mode === 'edit') {
                var $select = this.$select = this.$el.find('.select');
                $select.val(this.selected.join(':,:'));

                var data = [];

                (this.selected || []).forEach(i => {
                    let label = this.getLanguage().translateOption(i, this.name, this.scope);
                    if (this.translatedOptions) {
                        if (value in this.translatedOptions) {
                            label = this.translatedOptions[i];
                        }
                    }
                    data.push({
                        value: i,
                        label: label
                    });
                });

                $select.selectize({
                    create: true,
                    plugins: {
                        remove_button: {
                            label: ''
                        },
                        drag_drop: {}
                    },
                    options: data,
                    delimiter: ':,:',
                    labelField: 'label',
                    valueField: 'value',
                    onChange: value => {
                        const items = value.split(':,:').filter(item => !!item?.trim());
                        this.selected = Espo.Utils.clone(items);
                        this.trigger('change');
                    }
                });
            }

            if (this.mode == 'search') {
                this.renderSearch();
            }
        },

        renderSearch: function () {
            var $element = this.$element = this.$el.find('[name="' + this.name + '"]');

            var valueList = this.getSearchParamsData().valueList || this.searchParams.valueFront || [];
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

            var selectizeOptions = {
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

            this.$el.find('.selectize-dropdown-content').addClass('small');

            var type = this.$el.find('select.search-type').val();
            this.handleSearchType(type);
        },

        fetchFromDom: function () {
            var selected = [];
            this.$el.find('.list-group .list-group-item').each(function (i, el) {
                var value = $(el).data('value').toString();
                selected.push(value);
            });
            this.selected = selected;
        },

        getValueForDisplay: function () {
            return this.selected.map(function (item) {
                var label = null;
                if (this.translatedOptions != null) {
                    if (item in this.translatedOptions) {
                        label = this.getHelper().stripTags(this.translatedOptions[item]);
                    }
                }
                if (label === null) {
                    label = this.getHelper().stripTags(item);
                }
                if (label === '') {
                    label = this.translate('None');
                }
                return label;
            }, this).join(', ');
        },

        getItemHtml: function (value) {
            if (this.translatedOptions != null) {
                for (var item in this.translatedOptions) {
                    if (this.translatedOptions[item] == value) {
                        value = item;
                        break;
                    }
                }
            }

            value = value.toString();

            var valueSanitized = this.getHelper().stripTags(value);
            var valueSanitized = valueSanitized.replace(/"/g, '&quot;');

            var label = valueSanitized;
            if (this.translatedOptions) {
                label = ((value in this.translatedOptions) ? this.translatedOptions[value] : label);
                label = label.toString();
                label = this.getHelper().stripTags(label);
                label = label.replace(/"/g, '&quot;');
            }

            var html = '<div class="list-group-item" data-value="' + valueSanitized + '" style="cursor: default;">' + label +
            '&nbsp;<a href="javascript:" class="pull-right" data-value="' + valueSanitized + '" data-action="removeValue"><i class="ph ph-x"></i></a>' +
            '</div>';

            return html;
        },

        addValue: function (value) {
            if (this.selected.indexOf(value) == -1) {
                var html = this.getItemHtml(value);
                this.$list.append(html);
                this.selected.push(value);
                this.trigger('change');
            }
        },

        removeValue: function (value) {
            var valueSanitized = this.getHelper().stripTags(value).replace(/"/g, '\\"');

            this.$list.children('[data-value="' + valueSanitized + '"]').remove();
            var index = this.selected.indexOf(value);
            this.selected.splice(index, 1);
            this.trigger('change');
        },

        fetch: function () {
            let data = {},
                selected = this.selected || [];
            data[this.name] = Espo.Utils.clone(selected.length ? selected : null);
            return data;
        },


        clearSearch: function () {
            Dep.prototype.clearSearch.call(this);

            this.$element.get(0).selectize.clear();
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val() || 'anyOf';

            var arr = [];
            var arrFront = [];

            if (~['anyOf', 'noneOf'].indexOf(type)) {
                var valueList = this.$element.val().split(':,:');
                if (valueList.length == 1 && valueList[0] == '') {
                    valueList = [];
                }
            }

            if (type === 'anyOf') {
                var data = {
                    type: 'arrayAnyOf',
                    value: valueList,
                    data: {
                        type: 'anyOf',
                        valueList: valueList
                    }
                };
                if (!valueList.length) {
                    data.value = null;
                }
                return data;
            }

            if (type === 'noneOf') {
                var data = {
                    type: 'arrayNoneOf',
                    value: valueList,
                    data: {
                        type: 'noneOf',
                        valueList: valueList
                    }
                };
                return data;
            }

            if (type === 'isEmpty') {
                var data = {
                    type: 'arrayIsEmpty',
                    data: {
                        type: 'isEmpty'
                    }
                };
                return data;
            }

            if (type === 'isNotEmpty') {
                var data = {
                    type: 'arrayIsNotEmpty',
                    data: {
                        type: 'isNotEmpty'
                    }
                };
                return data;
            }
        },

        validateRequired: function () {
            if (this.isRequired()) {
                var value = this.model.get(this.name);
                if (!value || value.length == 0) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
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
                optgroup: this.getLanguage().translate('Fields'),
                operators: [
                    'array_any_of',
                    'array_none_of',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value, rule) {
                        if(!Array.isArray(value) || value.length === 0) {
                            return 'bad array';
                        }

                        return true;
                    }.bind(this),
                }
            };
        },

    });
});
