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

Espo.define('views/fields/varchar', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'varchar',

        listTemplate: 'fields/varchar/list',

        editTemplate: 'fields/varchar/edit',

        detailTemplate: 'fields/varchar/detail',

        searchTemplate: 'fields/varchar/search',

        searchTypeList: ['startsWith', 'contains', 'equals', 'endsWith', 'like', 'notContains', 'notEquals', 'notLike', 'isEmpty', 'isNotEmpty'],

        validationPattern: null,

        defaultFilterValue: '',

        events: {
            'keyup input.with-text-length': function (e) {
                this.updateTextCounter();
            },
            'focusin input': function () {
                this.applyDefaultValue()
            }
        },

        applyDefaultValue() {
            let defaultValue = this.model.defaults[this.name]
            if (defaultValue == null) {
                defaultValue = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'default'])
            }
            if (!this.model.get(this.name) && defaultValue) {
                this.model.set(this.name, defaultValue)
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.validations = Espo.utils.clone(this.validations);
            this.validations.push('pattern');


            let patternString = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'pattern']) || null;
            this.validationPattern = this.convertStrToRegex(patternString);
        },

        setupSearch: function () {
            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            if (
                this.model.get(this.name) !== null
                &&
                this.model.get(this.name) !== ''
                &&
                this.model.has(this.name)
            ) {
                data.isNotEmpty = true;
            }
            data.valueIsSet = this.model.has(this.name);

            if (this.mode === 'search') {
                if (typeof this.searchParams.value === 'string') {
                    this.searchData.value = this.searchParams.value;
                }
            }
            return data;
        },

        handleSearchType: function (type) {
            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                this.$el.find('input.main-element').addClass('hidden');
            } else {
                this.$el.find('input.main-element').removeClass('hidden');
            }
        },

        updateTextCounter() {
            let maxLength = this.params.maxLength;
            let countBytesInsteadOfCharacters = this.params.countBytesInsteadOfCharacters;
            if (!maxLength) {
                return;
            }

            let $input = this.$el.find('input');

            let text = $input.val();
            let textLength = this.getRealLength(text, countBytesInsteadOfCharacters);

            let $el = this.$el.find('.text-length-counter .current-length');

            $el.html(textLength);

            $input.css('border-color', '');
            $el.css('color', '');
            if (maxLength < textLength) {
                $input.css('border-color', 'red');
                $el.css('color', 'red');
            }
        },

        getRealLength(text, countBytesInsteadOfCharacters) {
            if (countBytesInsteadOfCharacters) {
                return encodeURI(text).split(/%..|./).length - 1;
            } else {
                return (text ? text.toString().length : 0);
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.mode === 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);
            }
            if (this.mode === 'edit') {
                this.updateTextCounter();

                const defaultValue = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'default']);
                if (defaultValue && typeof defaultValue === 'string' && defaultValue.includes('{{')
                    && defaultValue.includes('}}') && this.model.defaults[this.name] == null) {
                    // fetch default value from server
                    this.model.defaults = $.ajax({
                        url: this.model.name + '/action/Seed?silent=true',
                        type: 'GET',
                        dataType: 'json',
                        async: false,
                    }).responseJSON
                }

                if (this.inlineEditModeIsOn && this.isRequired() && this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'setDefaultOnlyIfRequired'])) {
                    this.applyDefaultValue()
                }
            }
        },

        setRequired: function () {
            if (this.mode === 'edit' && !this.isRequired() && this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'setDefaultOnlyIfRequired'])) {
                if (this.isRendered()) {
                    this.applyDefaultValue()
                } else {
                    this.once('after:render', function () {
                        this.applyDefaultValue();
                    }, this);
                }
            }
            Dep.prototype.setRequired.call(this)
        },

        fetch: function () {
            let data = {};

            let $el = this.$element;
            if ($el) {
                let value = $el.val();
                if (this.params.trim || this.forceTrim) {
                    if (typeof value.trim === 'function') {
                        value = value.trim();
                    }
                }
                data[this.name] = value ? value : null;
            }

            return data;
        },

        fetchSearch: function () {
            var type = this.$el.find('[name="' + this.name + '-type"]').val() || 'startsWith';

            var data;

            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                if (type == 'isEmpty') {
                    data = {
                        type: 'or',
                        value: [
                            {
                                type: 'isNull',
                                field: this.name,
                            },
                            {
                                type: 'equals',
                                field: this.name,
                                value: ''
                            }
                        ],
                        data: {
                            type: type
                        }
                    }
                } else {
                    data = {
                        type: 'and',
                        value: [
                            {
                                type: 'notEquals',
                                field: this.name,
                                value: ''
                            },
                            {
                                type: 'isNotNull',
                                field: this.name,
                                value: null
                            }
                        ],
                        data: {
                            type: type
                        }
                    }
                }
                return data;
            } else {
                const field = this.$element || this.$el.find("[name=" + this.name + "]");
                var value = field.val().toString().trim();
                data = {
                    value: value,
                    type: type,
                    data: {
                        type: type
                    }
                }
                return data;
            }
            return false;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        },

        validatePattern() {
            if (this.validationPattern) {
                let value = this.model.get(this.name);
                if (value !== '' && value !== null && !this.validationPattern.test(value)) {
                    let msg = this.getPatternValidationMessage();
                    if (msg) {
                        this.showValidationMessage(msg);
                    }
                    return true;
                }
            }
            return false;
        },

        getPatternValidationMessage() {
            return this.translate('dontMatchToPattern', 'exceptions', this.model.name)
                .replace('{field}', this.translate(this.name, 'fields', this.model.name))
                .replace('{pattern}', this.validationPattern);
        },

        convertStrToRegex(patternString) {
            if (patternString) {
                let flags = patternString.replace(/.*\/([gmixsuAJD]*)$/, '$1');
                let pattern = patternString.replace(new RegExp('^/(.*?)/' + flags + '$'), '$1');
                return new RegExp(pattern, flags);
            }

            return null;
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                operators: [
                    'contains',
                    'not_contains',
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this)
            };
        },

    });
});

