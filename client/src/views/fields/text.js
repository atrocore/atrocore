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
 */

Espo.define('views/fields/text', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'text',

        listTemplate: 'fields/text/list',

        detailTemplate: 'fields/text/detail',

        editTemplate: 'fields/text/edit',

        searchTemplate: 'fields/text/search',

        detailMaxLength: 400,

        detailMaxNewLineCount: 10,

        seeMoreText: false,

        rowsDefault: 10,

        rowsMin: 2,

        seeMoreDisabled: false,

        useDisabledTextareaInViewMode: false,

        searchTypeList: ['contains', 'startsWith', 'equals', 'endsWith', 'like', 'notContains', 'notLike', 'isEmpty', 'isNotEmpty'],

        events: {
            'click a[data-action="seeMoreText"]': function (e) {
                this.seeMoreText = true;
                this.reRender();
            },
            'keyup textarea.with-text-length': function (e) {
                this.updateTextCounter();
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.params.rows = this.params.rows || this.rowsDefault;
            this.detailMaxLength = this.params.lengthOfCut || this.detailMaxLength;
            this.seeMoreDisabled = this.seeMoreDisabled || this.params.seeMoreDisabled;
            this.useDisabledTextareaInViewMode = this.options.useDisabledTextareaInViewMode || this.params.useDisabledTextareaInViewMode || this.useDisabledTextareaInViewMode;
            this.autoHeightDisabled = this.options.autoHeightDisabled || this.params.autoHeightDisabled || this.autoHeightDisabled;

            if (this.params.rows < this.rowsMin) {
                this.rowsMin = this.params.rows;
            }
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
            if (this.mode === 'search') {
                if (typeof this.searchParams.value === 'string') {
                    this.searchData.value = this.searchParams.value;
                }
            }
            if (this.mode === 'edit') {
                if (this.autoHeightDisabled) {
                    data.rows = this.params.rows;
                } else {
                    data.rows = this.rowsMin;
                }
            }

            if (this.mode === 'detail' && this.useDisabledTextareaInViewMode) {
                data.rows = this.params.rows;
            }

            data.valueIsSet = this.model.has(this.name);
            data.useDisabledTextareaInViewMode = this.useDisabledTextareaInViewMode;

            return data;
        },

        handleSearchType: function (type) {
            if (~['isEmpty', 'isNotEmpty'].indexOf(type)) {
                this.$el.find('input.main-element').addClass('hidden');
            } else {
                this.$el.find('input.main-element').removeClass('hidden');
            }
        },

        getValueForDisplay: function () {
            var text = this.model.get(this.name);

            if (text && ((this.mode == 'detail' && !this.useDisabledTextareaInViewMode) || this.mode == 'list') && !this.seeMoreText && !this.seeMoreDisabled) {
                text = text.toString();

                var maxLength = this.detailMaxLength;

                var isCut = false;

                if (text.length > this.detailMaxLength) {
                    text = text.substr(0, this.detailMaxLength);
                    isCut = true;
                }

                var nlCount = (text.match(/\n/g) || []).length;
                if (nlCount > this.detailMaxNewLineCount) {
                    var a = text.split('\n').slice(0, this.detailMaxNewLineCount);
                    text = a.join('\n');
                    isCut = true;
                }

                if (isCut) {
                    text += ' ...\n[#see-more-text]';
                }
            }
            return text || '';
        },

        controlTextareaHeight: function (lastHeight) {
            var scrollHeight = this.$element.prop('scrollHeight');
            var clientHeight = this.$element.prop('clientHeight');

            if (typeof lastHeight === 'undefined' && clientHeight === 0) {
                setTimeout(this.controlTextareaHeight.bind(this), 10);
                return;
            }

            if (clientHeight === lastHeight) return;

            if (scrollHeight > clientHeight + 1) {
                var rows = this.$element.prop('rows');

                if (this.params.rows && rows >= this.params.rows) return;

                this.$element.attr('rows', rows + 1);
                this.controlTextareaHeight(clientHeight);
            }
            if (this.$element.val().length === 0) {
                this.$element.attr('rows', this.rowsMin);
            }
        },

        updateTextCounter() {
            let maxLength = this.params.maxLength;
            let countBytesInsteadOfCharacters = this.params.countBytesInsteadOfCharacters;
            if (!maxLength) {
                return;
            }

            let $textarea = this.$el.find('textarea');

            let text = this.$el.find('textarea').val();
            let textLength = this.getRealLength(text, countBytesInsteadOfCharacters);

            let $el = this.$el.find('.text-length-counter .current-length');

            $el.html(textLength);

            $textarea.css('border-color', '');
            $el.css('color', '');
            if (maxLength < textLength) {
                $textarea.css('border-color', 'red');
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
            if (this.mode == 'edit') {
                var text = this.getValueForDisplay();
                if (text) {
                    this.$element.val(text);
                }
                this.updateTextCounter();
            }
            if (this.mode == 'search') {
                var type = this.$el.find('select.search-type').val();
                this.handleSearchType(type);
            }

            if (this.mode === 'edit' && !this.autoHeightDisabled) {
                this.controlTextareaHeight();
                this.$element.on('input', function () {
                    this.controlTextareaHeight();
                }.bind(this));
            }
        },

        fetch: function () {
            var data = {};
            data[this.name] = this.$element ? this.$element.val() : null;
            if(data[this.name]==='') data[this.name]=null;
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
                var value = this.$element.val().toString().trim();
                value = value.trim();
                if (value) {
                    data = {
                        value: value,
                        type: type
                    }
                    return data;
                }
            }
            return false;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
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
                input: this.filterInput,
                valueGetter: this.filterValueGetter
            };
        },

    });
});

