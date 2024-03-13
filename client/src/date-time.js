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

Espo.define('date-time', [], function () {

    var DateTime = function () {

    };

    _.extend(DateTime.prototype, {

        internalDateFormat: 'YYYY-MM-DD',

        internalDateTimeFormat: 'YYYY-MM-DD HH:mm',

        internalDateTimeFullFormat: 'YYYY-MM-DD HH:mm:ss',

        dateFormat: 'MM/DD/YYYY',

        timeFormat: 'HH:mm',

        timeZone: null,

        weekStart: 1,

        readableDateFormatMap: {
            'DD.MM.YYYY': 'DD MMM',
            'DD/MM/YYYY': 'DD MMM',
        },

        readableShortDateFormatMap: {
            'DD.MM.YYYY': 'D MMM',
            'DD/MM/YYYY': 'D MMM',
        },

        hasMeridian: function () {
            return (new RegExp('A', 'i')).test(this.timeFormat);
        },

        getDateFormat: function () {
            return this.dateFormat;
        },

        getTimeFormat: function () {
            return this.timeFormat;
        },

        getDateTimeFormat: function () {
            return this.dateFormat + ' ' + this.timeFormat;
        },

        getReadableDateFormat: function () {
            return this.readableDateFormatMap[this.getDateFormat()] || 'MMM DD';
        },

        getReadableShortDateFormat: function () {
            return this.readableShortDateFormatMap[this.getDateFormat()] || 'MMM D';
        },

        getReadableDateTimeFormat: function () {
            return this.getReadableDateFormat() + ' ' + this.timeFormat;
        },

        getReadableShortDateTimeFormat: function () {
            return this.getReadableShortDateFormat() + ' ' + this.timeFormat;
        },

        fromDisplayDate: function (string) {
            if (!string) {
                return null;
            }
            var m = moment(string, this.dateFormat);
            if (!m.isValid()) {
                return -1;
            }
            return m.format(this.internalDateFormat);
        },

        getTimeZone: function () {
            return this.timeZone ? this.timeZone : 'UTC';
        },

        toDisplayDate: function (string) {
            if (!string || (typeof string != 'string')) {
                return '';
            }

            var m = moment(string, this.internalDateFormat);
            if (!m.isValid()) {
                return '';
            }

            return m.format(this.dateFormat);
        },

        fromDisplay: function (string) {
            if (!string) {
                return null;
            }
            var m;
            if (this.timeZone) {
                m = moment.tz(string, this.getDateTimeFormat(), this.timeZone).utc();
            } else {
                m = moment.utc(string, this.getDateTimeFormat());
            }

            if (!m.isValid()) {
                return -1;
            }
            return m.format(this.internalDateTimeFormat) + ':00';
        },

        fromDisplayDateTime: function (string) {
            return this.fromDisplay(string);
        },

        toDisplayDateTime: function (string) {
            return this.toDisplay(string);
        },

        toDisplay: function (string) {
            if (!string) {
                return '';
            }
            return this.toMoment(string).format(this.getDateTimeFormat());
        },

        getNowMoment: function () {
            return moment().tz(this.getTimeZone())
        },

        toMomentDate: function (string) {
            var m = moment.utc(string, this.internalDateFormat);
            return m;
        },

        toMoment: function (string) {
            var m = moment.utc(string, this.internalDateTimeFormat);
            if (this.timeZone) {
                m = m.tz(this.timeZone);
            }
            return m;
        },

        fromIso: function (string) {
            if (!string) {
                return '';
            }
            var m = moment(string).utc();
            return m.format(this.internalDateTimeFormat);
        },

        toIso: function (string) {
            if (!string) {
                return null;
            }
            return this.toMoment(string).format();
        },

        getToday: function () {
            return moment().tz(this.getTimeZone()).format(this.internalDateFormat);
        },

        getDateTimeShiftedFromNow: function (shift, type, multiplicity) {
            if (!multiplicity) {
                return moment.utc().add(type, shift).format(this.internalDateTimeFormat);
            } else {
                var unix = moment().unix();
                unix = unix - (unix % (multiplicity * 60));
                return moment.unix(unix).utc().add(type, shift).format(this.internalDateTimeFormat);
            }
        },

        getDateShiftedFromToday: function (shift, type) {
            return moment.tz(this.getTimeZone()).add(type, shift).format(this.internalDateFormat);
        },

        getNow: function (multiplicity) {
            if (!multiplicity) {
                return moment.utc().format(this.internalDateTimeFormat);
            } else {
                var unix = moment().unix();
                unix = unix - (unix % (multiplicity * 60));
                return moment.unix(unix).utc().format(this.internalDateTimeFormat);
            }
        },

        setSettingsAndPreferences: function (settings, preferences) {
            if (settings.has('dateFormat')) {
                this.dateFormat = settings.get('dateFormat');
            }
            if (settings.has('timeFormat')) {
                this.timeFormat = settings.get('timeFormat');
            }
            if (settings.has('timeZone')) {
                this.timeZone = settings.get('timeZone') || null;
                if (this.timeZone == 'UTC') {
                    this.timeZone = null;
                }
            }
            if (settings.has('weekStart')) {
                this.weekStart = settings.get('weekStart');
            }

            preferences.on('change', function (model) {
                if (model.has('dateFormat') && model.get('dateFormat') !== '') {
                    this.dateFormat = model.get('dateFormat');
                }
                if (model.has('timeFormat') && model.get('timeFormat') !== '') {
                    this.timeFormat = model.get('timeFormat');
                }
                if (model.has('timeZone') && model.get('timeZone') !== '') {
                    this.timeZone = model.get('timeZone');
                }
                if (model.has('weekStart') && model.get('weekStart') !== -1) {
                    this.weekStart = model.get('weekStart');
                }
                if (this.timeZone == 'UTC') {
                    this.timeZone = null;
                }
            }, this);
        },

        setLanguage: function (language) {
            moment.locale('en', {
                months: [
                    language.translate('January', 'monthNames'),
                    language.translate('February', 'monthNames'),
                    language.translate('March', 'monthNames'),
                    language.translate('April', 'monthNames'),
                    language.translate('May', 'monthNames'),
                    language.translate('June', 'monthNames'),
                    language.translate('July', 'monthNames'),
                    language.translate('August', 'monthNames'),
                    language.translate('September', 'monthNames'),
                    language.translate('October', 'monthNames'),
                    language.translate('November', 'monthNames'),
                    language.translate('December', 'monthNames')
                ],
                monthsShort: [
                    language.translate('Jan', 'monthNamesShort'),
                    language.translate('Feb', 'monthNamesShort'),
                    language.translate('Mar', 'monthNamesShort'),
                    language.translate('Apr', 'monthNamesShort'),
                    language.translate('May', 'monthNamesShort'),
                    language.translate('Jun', 'monthNamesShort'),
                    language.translate('Jul', 'monthNamesShort'),
                    language.translate('Aug', 'monthNamesShort'),
                    language.translate('Sep', 'monthNamesShort'),
                    language.translate('Oct', 'monthNamesShort'),
                    language.translate('Nov', 'monthNamesShort'),
                    language.translate('Dec', 'monthNamesShort')
                ],
                weekdays: [
                    language.translate('Sunday', 'dayNames'),
                    language.translate('Monday', 'dayNames'),
                    language.translate('Tuesday', 'dayNames'),
                    language.translate('Wednesday', 'dayNames'),
                    language.translate('Thursday', 'dayNames'),
                    language.translate('Friday', 'dayNames'),
                    language.translate('Saturday', 'dayNames')
                ],
                weekdaysShort: [
                    language.translate('Sun', 'dayNamesShort'),
                    language.translate('Mon', 'dayNamesShort'),
                    language.translate('Tue', 'dayNamesShort'),
                    language.translate('Wed', 'dayNamesShort'),
                    language.translate('Thu', 'dayNamesShort'),
                    language.translate('Fri', 'dayNamesShort'),
                    language.translate('Sat', 'dayNamesShort')
                ],
                weekdaysMin: [
                    language.translate('Su', 'dayNamesShortMin'),
                    language.translate('Mo', 'dayNamesShortMin'),
                    language.translate('Tu', 'dayNamesShortMin'),
                    language.translate('We', 'dayNamesShortMin'),
                    language.translate('Th', 'dayNamesShortMin'),
                    language.translate('Fr', 'dayNamesShortMin'),
                    language.translate('Sa', 'dayNamesShortMin')
                ],
            });
            moment.locale('en');
        },
    });

    return DateTime;

});

