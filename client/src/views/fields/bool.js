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

Espo.define('views/fields/bool', ['views/fields/base', 'lib!Selectize'], function (Dep) {

    return Dep.extend({

        type: 'bool',

        _template: '',

        notNull: true,

        defaultFilterValue: false,

        svelteComponent: null,

        setup() {
            Dep.prototype.setup.call(this);

            this.notNull = this.model.getFieldParam(this.name, 'notNull')
                ?? this.params?.notNull
                ?? this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'notNull']);

            if(this.notNull !== false) {
                this.notNull = true;
            }
        },

        setupSearch() {
            Dep.prototype.setupSearch.call(this);

            let value = null;
            if (this.searchParams && 'type' in this.searchParams) {
                if (this.searchParams.type === 'isTrue') {
                    value = 'true';
                }

                if (this.searchParams.type === 'isFalse') {
                    if ('fieldParams' in this.searchParams && this.searchParams.fieldParams.isAttribute) {
                        value = 'false';
                    }
                }
            }

            this.model.set(this.name, value);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const target = this.$el[0];

            if (!target) return;

            this.removeSvelteComponent();

            // For search mode derive the correct boolean initial value from searchParams
            // rather than the string set on the model by setupSearch.
            let initialValue;
            if (this.mode === 'search') {
                const type = this.searchParams?.type;
                if (type === 'isTrue') {
                    initialValue = true;
                } else if (type === 'isFalse') {
                    initialValue = false;
                } else {
                    initialValue = null;
                }
            } else {
                initialValue = this.model.get(this.name) ?? null;
            }

            this.svelteComponent = new Svelte.BoolField({
                target,
                props: {
                    name: this.name,
                    value: initialValue,
                    mode: this.mode,
                    notNull: this.notNull,
                    scope: this.model.name,
                    params: this.params || {},
                },
            });

            this.svelteComponent.$on('change', (event) => {
                const { name, value } = event.detail;
                this.model.set(name, value);
            });
        },

        removeSvelteComponent() {
            if (this.svelteComponent) {
                try {
                    this.svelteComponent.$destroy();
                } catch (e) {}
                this.svelteComponent = null;
            }
        },

        fetch() {
            if (this.svelteComponent) {
                return this.svelteComponent.fetch();
            }
            return { [this.name]: null };
        },

        clearSearch() {
            // Original only reset the checkbox (notNull=true case).
            if (this.svelteComponent && this.notNull) {
                this.svelteComponent.$set({ value: true });
            }
        },

        fetchSearch() {
            if (!this.svelteComponent) return {};

            const fetched = this.svelteComponent.fetch();
            const value = fetched[this.name];

            return {
                type: value === null || value === undefined ? 'isNull' : (value ? 'isTrue' : 'isFalse'),
            };
        },

        populateSearchDefaults() {
            if (this.svelteComponent) {
                this.svelteComponent.$set({ value: true });
            }
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'boolean',
                optgroup: this.getLanguage().translate('Fields'),
                operators: [
                    'equal',
                    'not_equal',
                    'is_null',
                    'is_not_null'
                ],
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
            };
        },

        remove(dontEmpty) {
            this.removeSvelteComponent();
            Dep.prototype.remove.call(this, dontEmpty);
        },
    });
});