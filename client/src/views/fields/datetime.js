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

Espo.define('views/fields/datetime', 'views/fields/date', function (Dep) {

    return Dep.extend({

        type: 'datetime',

        editTemplate: 'fields/datetime/edit',

        validations: ['required', 'datetime', 'after', 'before'],

        searchTypeList: ['lastSevenDays', 'ever', 'isEmpty', 'currentMonth', 'lastMonth', 'nextMonth', 'currentQuarter', 'lastQuarter', 'currentYear', 'lastYear', 'today', 'past', 'future', 'lastXDays', 'nextXDays', 'olderThanXDays', 'afterXDays', 'on', 'after', 'before', 'between'],

        timeFormatMap: {
            'HH:mm': 'H:i',
            'hh:mm A': 'h:i A',
            'hh:mm a': 'h:i a',
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.date = data.time = '';
            var value = this.getDateTime().toDisplay(this.model.get(this.name));
            if (value) {
                data.date = value.substr(0, value.indexOf(' '));
                data.time = value.substr(value.indexOf(' ') + 1);
            }
            return data;
        },

        getDateStringValue: function () {
            if (this.mode === 'detail' && !this.model.has(this.name)) {
                return '...';
            }
            var value = this.model.get(this.name);
            if (!value) {
                if (this.mode == 'edit' || this.mode == 'search' || this.mode === 'list' || this.mode == 'listLink') {
                    return '';
                }
                return this.translate('None');
            }

            if (this.mode == 'list' || this.mode == 'detail' || this.mode == 'listLink') {
                if (this.getConfig().get('readableDateFormatDisabled') || this.params.useNumericFormat) {
                    return this.getDateTime().toDisplayDateTime(value);
                }

                var d = this.getDateTime().toMoment(value);
                var now = moment().tz(this.getDateTime().timeZone || 'UTC');
                var dt = now.clone().startOf('day');

                var ranges = {
                    'today': [dt.unix(), dt.add(1, 'days').unix()],
                    'tomorrow': [dt.unix(), dt.add(1, 'days').unix()],
                    'yesterday': [dt.add(-3, 'days').unix(), dt.add(1, 'days').unix()]
                };

                if (d.unix() > ranges['today'][0] && d.unix() < ranges['today'][1]) {
                    return this.translate('Today') + ' ' + d.format(this.getDateTime().timeFormat);
                } else if (d.unix() > ranges['tomorrow'][0] && d.unix() < ranges['tomorrow'][1]) {
                    return this.translate('Tomorrow') + ' ' + d.format(this.getDateTime().timeFormat);
                } else if (d.unix() > ranges['yesterday'][0] && d.unix() < ranges['yesterday'][1]) {
                    return this.translate('Yesterday') + ' ' + d.format(this.getDateTime().timeFormat);
                }

                var readableFormat = this.getDateTime().getReadableDateFormat();

                if (d.format('YYYY') == now.format('YYYY')) {
                    return d.format(readableFormat) + ' ' + d.format(this.getDateTime().timeFormat);
                } else {
                    return d.format(readableFormat + ', YYYY') + ' ' + d.format(this.getDateTime().timeFormat);
                }
            }

            return this.getDateTime().toDisplay(value);
        },

        initTimepicker: function () {
            var $time = this.$time;
            $time.timepicker({
                step: 30,
                scrollDefaultNow: true,
                timeFormat: this.timeFormatMap[this.getDateTime().timeFormat]
            });
            $time.parent().find('button.time-picker-btn').on('click', function () {
                $time.timepicker('show');
            });
        },

        setDefaultTime: function () {
            var d = moment('2014-01-01 00:00').format(this.getDateTime().getDateTimeFormat()) || '';
            var index = d.indexOf(' ');
            if (~index) {
                this.$time.val(d.substr(index + 1));
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'edit') {
                var $date = this.$date = this.$element;
                var $time = this.$time = this.$el.find('input[name="' + this.name + '-time"]');
                this.initTimepicker();

                this.$element.on('change.datetime', function (e) {
                    if (this.$element.val() && !$time.val()) {
                        this.setDefaultTime();
                        this.trigger('change');
                    }
                }.bind(this));

                var timeout = false;
                var changeCallback = function () {
                    if (!timeout) {
                        this.trigger('change');
                    }
                    timeout = true;
                    setTimeout(function () {
                        timeout = false;
                    }, 100)
                }.bind(this);
                $time.on('change', changeCallback);
            }
        },

        update: function (value) {
            if (this.mode == 'edit') {
                var formatedValue = this.getDateTime().toDisplay(value);
                var arr = formatedValue.split(' ');
                this.$date.val(arr[0]);
                this.$time.val(arr[1]);
            } else {
                this.setup();
                this.render();
            }
        },

        parse: function (string) {
            return this.getDateTime().fromDisplay(string);
        },

        fetch: function () {
            var data = {};

            var date = this.$el.find('[name="' + this.name + '"]').val();
            var time = this.$el.find('[name="' + this.name + '-time"]').val();

            var value = null;
            if (date != '' && time != '') {
                value = this.parse(date + ' ' + time);
            }
            data[this.name] = value;
            return data;
        },

        validateDatetime: function () {
            if (this.model.get(this.name) === -1) {
                var msg = this.translate('fieldShouldBeDatetime', 'messages').replace('{field}', this.getLabelText());
                this.showValidationMessage(msg);
                return true;
            }
        },

        fetchSearch: function () {
            var data = Dep.prototype.fetchSearch.call(this);

            if (data) {
                data.dateTime = true;
            }
            return data;
        },

    });
});

