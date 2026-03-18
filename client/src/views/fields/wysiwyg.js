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

// lib!Summernote is listed as a dependency to ensure the script is loaded before the Svelte
// component mounts and accesses $.summernote. It is not used directly in this file.
Espo.define('views/fields/wysiwyg', ['views/fields/base', 'lib!Summernote'], function (Dep) {

    return Dep.extend({

        type: 'wysiwyg',

        _template: '',

        svelteComponent: null,

        defaultFilterValue: '',

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.mode === 'edit') {
                this.listenTo(this.model, 'change:isHtml', () => {
                    if (!this.svelteComponent || !this.isRendered()) return;
                    const isHtml = !this.model.has('isHtml') || this.model.get('isHtml');
                    this.svelteComponent.$set({ isHtml });
                });
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

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            const target = this.$el[0];
            if (!target) return;

            this.removeSvelteComponent();

            const searchType = this.mode === 'search' ? (this.getSearchType() || 'startsWith') : 'startsWith';
            const searchValue = (this.mode === 'search' && typeof this.searchParams?.value === 'string')
                ? this.searchParams.value
                : '';

            const isHtml = !this.model.has('isHtml') || this.model.get('isHtml');

            this.svelteComponent = new Svelte.WysiwygField({
                target,
                props: {
                    name: this.name,
                    value: this.model.get(this.name) ?? null,
                    mode: this.mode,
                    params: this.params || {},
                    isHtml,
                    hasIsHtml: this.model.has('isHtml'),
                    useIframe: !!(this.params && this.params.useIframe),
                    iframeStylesheet: this.mode === 'detail' ? this.getIframeStylesheet() : '',
                    searchType,
                    searchValue,
                    wysiwygView: this,
                },
            });
        },

        getIframeStylesheet: function () {
            try {
                return this.getBasePath() + this.getThemeManager().getIframeStylesheet();
            } catch (e) {
                return '';
            }
        },

        removeSvelteComponent: function () {
            if (this.svelteComponent) {
                try {
                    this.svelteComponent.$destroy();
                } catch (e) {}
                this.svelteComponent = null;
            }
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams?.typeFront || this.searchParams?.type;
        },

        inlineEditFocusing: function () {
            requestAnimationFrame(() => {
                this.$el.find('.note-editable').focus();
            });
        },

        createQueryBuilderFilter() {
            let operators = ['contains', 'not_contains', 'equal', 'not_equal', 'is_null', 'is_not_null'];
            if (this.getConfig().get('fuzzySearchAvailable')) {
                operators.push('similar', 'word_similar');
            }
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                optgroup: this.getLanguage().translate('Fields'),
                operators: operators,
                input: this.filterInput.bind(this),
                valueGetter: this.filterValueGetter.bind(this),
                validation: {
                    callback: function (value) {
                        if (value === null || typeof value !== 'string') {
                            return 'bad string';
                        }
                        return true;
                    }.bind(this),
                },
            };
        },

        remove: function (dontEmpty) {
            this.removeSvelteComponent();
            Dep.prototype.remove.call(this, dontEmpty);
        },
    });
});
