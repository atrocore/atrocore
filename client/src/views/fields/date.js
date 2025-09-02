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

Espo.define('views/fields/date', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'date',

        listTemplate: 'fields/date/list',

        listLinkTemplate: 'fields/date/list-link',

        detailTemplate: 'fields/date/detail',

        editTemplate: 'fields/date/edit',

        searchTemplate: 'fields/date/search',

        validations: ['required', 'date', 'after', 'before', 'afterOrEqual','beforeOrEqual'],

        searchTypeList: ['lastSevenDays', 'ever', 'isEmpty', 'currentMonth', 'lastMonth', 'nextMonth', 'currentQuarter', 'lastQuarter', 'currentYear', 'lastYear', 'today', 'past', 'future', 'lastXDays', 'nextXDays', 'olderThanXDays', 'afterXDays', 'on', 'after', 'before', 'between'],

        data: function () {
            var data = Dep.prototype.data.call(this);
            if (this.mode === 'search') {
                this.searchData.dateValue = this.getDateTime().toDisplayDate(this.searchParams.dateValue);
                this.searchData.dateValueTo = this.getDateTime().toDisplayDate(this.searchParams.dateValueTo);
            }
            data.dateValue = this.getDateStringValue();
            return data;
        },

        setupSearch: function () {
            this.events = _.extend({
                'change select.search-type': function (e) {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        stringifyDateValue: function (value) {
            if (!value) {
                if (this.mode == 'edit' || this.mode == 'search' || this.mode == 'list' || this.mode == 'listLink') {
                    return '';
                }
                return this.translate('None');
            }

            if (this.mode == 'list' || this.mode == 'detail' || this.mode == 'listLink') {
                if (this.getConfig().get('readableDateFormatDisabled') || this.params.useNumericFormat) {
                    return this.getDateTime().toDisplayDate(value);
                }

                var d = moment.tz(value + ' OO:OO:00', this.getDateTime().internalDateTimeFormat, this.getDateTime().getTimeZone());

                var today = moment().tz(this.getDateTime().getTimeZone()).startOf('day');
                var dt = today.clone();

                var ranges = {
                    'today': [dt.unix(), dt.add(1, 'days').unix()],
                    'tomorrow': [dt.unix(), dt.add(1, 'days').unix()],
                    'yesterday': [dt.add(-3, 'days').unix(), dt.add(1, 'days').unix()]
                };

                if (d.unix() >= ranges['today'][0] && d.unix() < ranges['today'][1]) {
                    return this.translate('Today');
                } else if (d.unix() >= ranges['tomorrow'][0] && d.unix() < ranges['tomorrow'][1]) {
                    return this.translate('Tomorrow');
                } else if (d.unix() >= ranges['yesterday'][0] && d.unix() < ranges['yesterday'][1]) {
                    return this.translate('Yesterday');
                }
            }

            return this.getDateTime().toDisplayDate(value);
        },

        getDateStringValue: function () {
            if (this.mode === 'detail' && !this.model.has(this.name)) {
                return '...';
            }
            var value = this.model.get(this.name);
            return this.stringifyDateValue(value);
        },

        afterRender: function () {
            if (this.mode == 'edit' || this.mode == 'search') {
                this.$element = this.$el.find('[name="' + this.name + '"]');

                var wait = false;
                this.$element.on('change', function () {
                    if (!wait) {
                        this.trigger('change');
                        wait = true;
                        setTimeout(function () {
                            wait = false
                        }, 100);
                    }
                }.bind(this));

                var options = {
                    format: this.getDateTime().dateFormat.toLowerCase(),
                    weekStart: this.getDateTime().weekStart,
                    autoclose: true,
                    todayHighlight: true,
                };

                var language = this.getConfig().get('language');

                if (!(language in $.fn.datepicker.dates)) {
                    $.fn.datepicker.dates[language] = {
                        days: [
                            this.translate('Sunday', 'dayNames'),
                            this.translate('Monday', 'dayNames'),
                            this.translate('Tuesday', 'dayNames'),
                            this.translate('Wednesday', 'dayNames'),
                            this.translate('Thursday', 'dayNames'),
                            this.translate('Friday', 'dayNames'),
                            this.translate('Saturday', 'dayNames')
                        ],
                        daysShort: [
                            this.translate('Sun', 'dayNamesShort'),
                            this.translate('Mon', 'dayNamesShort'),
                            this.translate('Tue', 'dayNamesShort'),
                            this.translate('Wed', 'dayNamesShort'),
                            this.translate('Thu', 'dayNamesShort'),
                            this.translate('Fri', 'dayNamesShort'),
                            this.translate('Sat', 'dayNamesShort')
                        ],
                        daysMin: [
                            this.translate('Su', 'dayNamesShortMin'),
                            this.translate('Mo', 'dayNamesShortMin'),
                            this.translate('Tu', 'dayNamesShortMin'),
                            this.translate('We', 'dayNamesShortMin'),
                            this.translate('Th', 'dayNamesShortMin'),
                            this.translate('Fr', 'dayNamesShortMin'),
                            this.translate('Sa', 'dayNamesShortMin')
                        ],
                        months: [
                            this.translate('January', 'monthNames'),
                            this.translate('February', 'monthNames'),
                            this.translate('March', 'monthNames'),
                            this.translate('April', 'monthNames'),
                            this.translate('May', 'monthNames'),
                            this.translate('June', 'monthNames'),
                            this.translate('July', 'monthNames'),
                            this.translate('August', 'monthNames'),
                            this.translate('September', 'monthNames'),
                            this.translate('October', 'monthNames'),
                            this.translate('November', 'monthNames'),
                            this.translate('December', 'monthNames')
                        ],
                        monthsShort: [
                            this.translate('Jan', 'monthNamesShort'),
                            this.translate('Feb', 'monthNamesShort'),
                            this.translate('Mar', 'monthNamesShort'),
                            this.translate('Apr', 'monthNamesShort'),
                            this.translate('May', 'monthNamesShort'),
                            this.translate('Jun', 'monthNamesShort'),
                            this.translate('Jul', 'monthNamesShort'),
                            this.translate('Aug', 'monthNamesShort'),
                            this.translate('Sep', 'monthNamesShort'),
                            this.translate('Oct', 'monthNamesShort'),
                            this.translate('Nov', 'monthNamesShort'),
                            this.translate('Dec', 'monthNamesShort')
                        ],
                        today: this.translate('Today'),
                        clear: this.translate('Clear'),
                    };
                }

                options.language = language;

                var $datePicker = this.$element.datepicker(options).on('show', function (e) {
                    $('body > .datepicker.datepicker-dropdown').css('z-index', 2200);
                }.bind(this));

                if (this.mode == 'search') {
                    var $elAdd = this.$el.find('input[name="' + this.name + '-additional"]');
                    $elAdd.datepicker(options).on('show', function (e) {
                        $('body > .datepicker.datepicker-dropdown').css('z-index', 2200);
                    }.bind(this));
                    $elAdd.parent().find('button.date-picker-btn').on('click', function (e) {
                        $elAdd.datepicker('show');
                    });
                }

                this.$element.parent().find('button.date-picker-btn').on('click', function (e) {
                    this.$element.datepicker('show');
                }.bind(this));


                if (this.mode == 'search') {
                    var $searchType = this.$el.find('select.search-type');
                    this.handleSearchType($searchType.val());
                }
            }
        },

        handleSearchType: function (type) {
            this.$el.find('div.primary').addClass('hidden');
            this.$el.find('div.additional').addClass('hidden');
            this.$el.find('div.additional-number').addClass('hidden');

            if (~['on', 'notOn', 'after', 'before'].indexOf(type)) {
                this.$el.find('div.primary').removeClass('hidden');
            } else if (~['lastXDays', 'nextXDays', 'olderThanXDays', 'afterXDays'].indexOf(type)) {
                this.$el.find('div.additional-number').removeClass('hidden');
            } else if (type == 'between') {
                this.$el.find('div.primary').removeClass('hidden');
                this.$el.find('div.additional').removeClass('hidden');
            }
        },

        parseDate: function (string) {
            return this.getDateTime().fromDisplayDate(string);
        },

        parse: function (string) {
            return this.parseDate(string);
        },

        fetch: function () {
            var data = {};
            data[this.name] = this.$element ? this.parse(this.$element.val()) : null;
            return data;
        },

        clearSearch: function () {
            Dep.prototype.clearSearch.call(this);

            this.$el.find('[name="' + this.name + '-additional"]').val('');
            this.$el.find('[name="' + this.name + '-number"]').val('');
        },

        fetchSearch: function () {
            var value = this.parseDate(this.$element.val());

            var type = this.$el.find('[name="' + this.name + '-type"]').val();
            var data;

            if (type == 'between') {
                var valueTo = this.parseDate(this.$el.find('[name="' + this.name + '-additional"]').val());

                data = {
                    type: type,
                    value: [value, valueTo],
                    dateValue: value,
                    dateValueTo: valueTo
                };
            } else if (~['lastXDays', 'nextXDays', 'olderThanXDays', 'afterXDays'].indexOf(type)) {
                var number = this.$el.find('[name="' + this.name + '-number"]').val();
                data = {
                    type: type,
                    value: number,
                    number: number
                };
            } else if (~['on', 'notOn', 'after', 'before'].indexOf(type)) {
                data = {
                    type: type,
                    value: value,
                    dateValue: value
                };
            } else if (type === 'isEmpty') {
                data = {
                    type: 'isNull',
                    data: {
                        type: type
                    }
                }
            } else {
                data = {
                    type: type
                };
            }
            return data;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.name) === null) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        validateDate: function () {
            if (this.model.get(this.name) === -1) {
                var msg = this.translate('fieldShouldBeDate', 'messages').replace('{field}', this.getLabelText());
                this.showValidationMessage(msg);
                return true;
            }
        },

        validateAfter: function () {
            var field = this.model.getFieldParam(this.name, 'after');
            if (field) {
                var value = this.model.get(this.name);
                var otherValue = this.model.get(field);
                if (value && otherValue) {
                    if (moment(value).unix() <= moment(otherValue).unix()) {
                        var msg = this.translate('fieldShouldAfter', 'messages').replace('{field}', this.getLabelText())
                            .replace('{otherField}', this.translate(field, 'fields', this.model.name));

                        this.showValidationMessage(msg);
                        return true;
                    }
                }
            }
        },

        validateAfterOrEqual: function () {
            var field = this.model.getFieldParam(this.name, 'afterOrEqual');
            if (field) {
                var value = this.model.get(this.name);
                var otherValue = this.model.get(field);
                if (value && otherValue) {
                    if (moment(value).unix() < moment(otherValue).unix()) {
                        var msg = this.translate('fieldShouldAfterOrEqual', 'messages').replace('{field}', this.getLabelText())
                            .replace('{otherField}', this.translate(field, 'fields', this.model.name));

                        this.showValidationMessage(msg);
                        return true;
                    }
                }
            }
        },

        validateBefore: function () {
            var field = this.model.getFieldParam(this.name, 'before');
            if (field) {
                var value = this.model.get(this.name);
                var otherValue = this.model.get(field);
                if (value && otherValue) {
                    if (moment(value).unix() >= moment(otherValue).unix()) {
                        var msg = this.translate('fieldShouldBefore', 'messages').replace('{field}', this.getLabelText())
                            .replace('{otherField}', this.translate(field, 'fields', this.model.name));
                        this.showValidationMessage(msg);
                        return true;
                    }
                }
            }
        },

        validateBeforeOrEqual: function () {
            var field = this.model.getFieldParam(this.name, 'beforeOrEqual');
            if (field) {
                var value = this.model.get(this.name);
                var otherValue = this.model.get(field);
                if (value && otherValue) {
                    if (moment(value).unix() > moment(otherValue).unix()) {
                        var msg = this.translate('fieldShouldBeforeOrEqual', 'messages').replace('{field}', this.getLabelText())
                            .replace('{otherField}', this.translate(field, 'fields', this.model.name));
                        this.showValidationMessage(msg);
                        return true;
                    }
                }
            }
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'date',
                optgroup: this.getLanguage().translate('Fields'),
                operators: [
                    'equal',
                    'not_equal',
                    'less',
                    'less_or_equal',
                    'greater',
                    'greater_or_equal',
                    'between',
                    'last_x_days',
                    'next_x_days',
                    'current_month',
                    'last_month',
                    'next_month',
                    'current_year',
                    'last_year',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function(value, rule) {
                        if(['last_x_days', 'next_x_days'].includes(rule.operator.type)) {
                            if(value == null || isNaN(value)) {
                                return 'bad int';
                            }
                            return  true;
                        }
                        if(rule.operator.type ==='between') {
                            if((!Array.isArray(value) || value.length !== 2)) {
                                return 'bad between';
                            }
                            return  true;
                        }

                        if(value === null) {
                            return 'bad date';
                        }
                        return true;
                    }
                }
            };
        },

    });
});

