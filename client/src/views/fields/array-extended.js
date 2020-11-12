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

Espo.define('views/fields/array-extended', 'views/fields/array',
    Dep => Dep.extend({

        _timeouts: {},

        events: _.extend({
            'click [data-action="addNewValue"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.addNewValue();
            },
            'click [data-action="removeGroup"]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.removeGroup($(e.currentTarget));
            },
            'change input[data-name][data-index]': function (e) {
                e.stopPropagation();
                e.preventDefault();
                this.trigger('change');
            }
        }, Dep.prototype.events),

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.resetValue();
                this.setMode(this.mode);
                this.reRender();
            });

            this.langFieldNames = this.getLangFieldNames();

            this.updateSelectedComplex();
            const eventStr = this.langFieldNames.reduce((prev, curr) => `${prev} change:${curr}`, `change:${this.name}`);
            this.listenTo(this.model, eventStr, () => this.updateSelectedComplex());

            this.listenTo(this.model, 'change:isMultilang', () => {
                this.setMode(this.mode);
                this.reRender();
            });

            this.listenTo(this, 'change', () => this.reRender());
        },

        afterRender: function () {
            if (this.mode === 'edit') {
                this.$list = this.$el.find('.list-group');
                var $select = this.$select = this.$el.find('.select');

                if (!this.params.options) {
                    $select.on('keypress', function (e) {
                        if (e.keyCode === 13) {
                            var value = $select.val().toString();
                            if (this.noEmptyString) {
                                if (value === '') {
                                    return;
                                }
                            }
                            this.addValue(value);
                            $select.val('');
                        }
                    }.bind(this));
                }
            }

            if (this.mode === 'search') {
                this.renderSearch();
            }

            let deletedRow = $("input[value=todel]").parents('.list-group-item');
            deletedRow.find('a[data-action=removeGroup]').remove();
            deletedRow.hide();

            let removeGroupButtons = $('a[data-action=removeGroup]');
            if (removeGroupButtons.length === 1) {
                removeGroupButtons.remove();
            }
        },

        getLangFieldNames() {
            return (this.getConfig().get('inputLanguageList') || []).map(item => {
                return item.split('_').reduce((prev, curr) => {
                    prev = prev + Espo.Utils.upperCaseFirst(curr.toLowerCase());
                    return prev;
                }, this.name);
            });
        },

        updateSelectedComplex() {
            this.selectedComplex = {
                [this.name]: Espo.Utils.cloneDeep(this.model.get(this.name)) || []
            };
            this.langFieldNames.forEach(name => {
                this.selectedComplex[name] = Espo.Utils.cloneDeep(this.model.get(name)) || []
            });
        },

        setMode: function (mode) {
            // prepare mode
            this.mode = mode;

            // prepare type
            let type = (this.model.get('type') === 'unit') ? 'enum' : 'array';

            // set template
            this.template = 'fields/' + Espo.Utils.camelCaseToHyphen(type) + '/' + this.mode;

            if (this.isEnums() && mode !== 'list') {
                this.template = 'fields/array-extended/' + mode;
            }
        },

        addNewValue() {
            let data = {
                [this.name]: (this.selectedComplex[this.name] || []).concat([''])
            };
            this.langFieldNames.forEach(name => {
                data[name] = (this.selectedComplex[name] || []).concat([''])
            });
            this.selectedComplex = data;
            this.reRender();
            this.trigger('change');
        },

        removeGroup(el) {
            let index = el.data('index');
            let value = this.selectedComplex[this.name] || [];
            value[index] = 'todel';
            // value.splice(index, 1);
            let data = {
                [this.name]: value
            };
            this.langFieldNames.forEach(name => {
                let value = this.selectedComplex[name] || [];
                value[index] = 'todel';
                // value.splice(index, 1);
                data[name] = value;
            });
            this.selectedComplex = data;
            this.reRender();
            this.trigger('change');
        },

        data() {
            let data = Dep.prototype.data.call(this);

            data.name = this.name;
            data = this.modifyDataByType(data);

            return data;
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            data = this.modifyFetchByType(data);

            return data;
        },

        modifyFetchByType(data) {
            let fetchedData = data;
            if (this.model.get('type') === 'unit') {
                fetchedData = {};
                fetchedData[this.name] = [this.$el.find(`[name="${this.name}"]`).val()];
            }

            if (this.isEnums()) {
                this.fetchFromDom();
                Object.entries(this.selectedComplex).forEach(([key, value]) => data[key] = value);
            }

            return fetchedData;
        },

        fetchFromDom() {
            if (this.isEnums()) {
                const data = {};
                data[this.name] = [];
                this.langFieldNames.forEach(name => data[name] = []);
                this.$el.find('.option-group').each((index, element) => {
                    $(element).find('.option-item input').each((i, el) => {
                        const $el = $(el);
                        const name = $el.data('name').toString();
                        data[name][index] = $el.val().toString();
                    });
                });
                this.selectedComplex = data;
            } else {
                Dep.prototype.fetchFromDom.call(this);
            }
        },

        validateRequired() {
            const values = this.model.get(this.name);
            let error = !values || !values.length;
            values.forEach((value, i) => {
                if (!value) {
                    let msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate('Value'));
                    this.showValidationMessage(msg, `input[data-name="${this.name}"][data-index="${i}"]`);
                    error = true;
                }
            });

            return error;
        },

        showValidationMessage: function (message, target) {
            var $el;

            target = target || '.array-control-container';

            if (typeof target === 'string' || target instanceof String) {
                $el = this.$el.find(target);
            } else {
                $el = $(target);
            }

            if (!$el.size() && this.$element) {
                $el = this.$element;
            }

            $el.css('border-color', '#a94442');
            $el.css('-webkit-box-shadow', 'inset 0 1px 1px rgba(0, 0, 0, 0.075)');
            $el.css('-moz-box-shadow', 'inset 0 1px 1px rgba(0, 0, 0, 0.075)');
            $el.css('box-shadow', 'inset 0 1px 1px rgba(0, 0, 0, 0.075)');

            $el.popover({
                placement: 'bottom',
                container: 'body',
                content: message,
                trigger: 'manual',
                html: true
            }).popover('show');

            var isDestroyed = false;

            $el.closest('.field').one('mousedown click', function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            });

            this.once('render remove', function () {
                if (isDestroyed) return;
                if ($el) {
                    $el.popover('destroy');
                    isDestroyed = true;
                }
            });

            if (this._timeouts[target]) {
                clearTimeout(this._timeouts[target]);
            }

            this._timeouts[target] = setTimeout(function () {
                if (isDestroyed) return;
                $el.popover('destroy');
                isDestroyed = true;
            }, 3000);
        },

        modifyDataByType(data) {
            data = Espo.Utils.cloneDeep(data);
            if (this.model.get('type') === 'unit') {
                let options = Object.keys(this.getConfig().get('unitsOfMeasure') || {});
                data.params.options = options;
                let translatedOptions = {};
                options.forEach(item => translatedOptions[item] = this.getLanguage().get('Global', 'measure', item));
                data.translatedOptions = translatedOptions;
                let value = this.model.get(this.name);
                if (
                    value !== null
                    &&
                    value !== ''
                    ||
                    value === '' && (value in (translatedOptions || {}) && (translatedOptions || {})[value] !== '')
                ) {
                    data.isNotEmpty = true;
                }
            }

            if (this.isEnums()) {
                data.optionGroups = (this.selectedComplex[this.name] || []).map((item, index) => {
                    let options = [
                        {
                            name: this.name,
                            value: item,
                            shortLang: ''
                        }
                    ];

                    if (this.model.get('isMultilang') || !!(this.model.get('multilangField'))) {
                        (this.langFieldNames || []).forEach(function (name) {
                            options.push(
                                {
                                    name: name,
                                    value: (this.selectedComplex[name] || [])[index],
                                    shortLang: name.slice(-4, -2).toLowerCase() + '_' + name.slice(-2).toUpperCase()
                                }
                            );
                        }, this);
                    }

                    return {options: options};
                });
            }

            return data;
        },

        isEnums() {
            return this.model.get('type') === 'enum' || this.model.get('type') === 'multiEnum';
        },

        resetValue() {
            [this.name, ...this.langFieldNames].forEach(name => this.selectedComplex[name] = null);
            this.model.set(this.selectedComplex);
        }

    })
);