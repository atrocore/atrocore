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

Espo.define('views/fields/text', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'text',

        _template: '',

        defaultFilterValue: '',

        searchTypeList: ['contains', 'startsWith', 'equals', 'endsWith', 'like', 'notContains', 'notLike', 'isEmpty', 'isNotEmpty'],

        svelteComponent: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.params.rowsMin) this.params.rowsMin = 2;
            if (!this.params.rowsMax) this.params.rowsMax = 10;
            if (this.params.rowsMax < this.params.rowsMin) {
                this.params.rowsMin = this.params.rowsMax;
            }

            this.setScriptDefaultValue();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            const target = this.$el[0];
            if (!target) return;

            this.removeSvelteComponent();

            const searchType = this.mode === 'search' ? (this.getSearchType() || 'startsWith') : 'startsWith';
            const searchValue = (this.mode === 'search' && typeof this.searchParams?.value === 'string')
                ? this.searchParams.value
                : '';

            this.svelteComponent = new Svelte.TextField({
                target,
                props: {
                    name: this.name,
                    value: this.model.get(this.name) ?? '',
                    mode: this.mode,
                    scope: this.model.name,
                    params: this.params || {},
                    searchType,
                    searchValue,
                },
            });

            this.svelteComponent.$on('change', () => {
                this.trigger('change');
            });
        },

        removeSvelteComponent: function () {
            if (this.svelteComponent) {
                try {
                    this.svelteComponent.$destroy();
                } catch (e) {}
                this.svelteComponent = null;
            }
        },

        fetch: function () {
            if (this.svelteComponent) {
                return this.svelteComponent.fetch();
            }
            return { [this.name]: null };
        },

        fetchSearch: function () {
            if (this.svelteComponent) {
                return this.svelteComponent.fetchSearch();
            }
            return false;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront || this.searchParams.type;
        },

        inlineEditFocusing() {
            requestAnimationFrame(() => {
                const $textarea = this.$el.find('textarea');
                $textarea.focus();
                const val = $textarea.val();
                $textarea[0].setSelectionRange(val.length, val.length);
            });
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                optgroup: this.getLanguage().translate('Fields'),
                operators: [
                    'contains',
                    'not_contains',
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value, rule) {
                        if (value === null || typeof value !== 'string') {
                            return 'bad string';
                        }
                        return true;
                    }.bind(this),
                }
            };
        },

        remove: function (dontEmpty) {
            this.removeSvelteComponent();
            Dep.prototype.remove.call(this, dontEmpty);
        },
    });
});