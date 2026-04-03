/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

// lib!EasyMDE is listed as a dependency to ensure the script is loaded before the Svelte
// component mounts and accesses window.EasyMDE. It is not used directly in this file.
Espo.define('views/fields/markdown', ['views/fields/base', 'lib!EasyMDE'], function (Dep) {

    return Dep.extend({

        _template: '',

        svelteComponent: null,

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

            this.svelteComponent = new Svelte.MarkdownField({
                target,
                props: {
                    name: this.name,
                    value: this.model.get(this.name) ?? null,
                    mode: this.mode,
                    params: this.params || {},
                    searchType,
                    searchValue,
                    markdownView: this,
                    mentions: (this.model.get('data') || {}).mentions || {}
                },
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

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams?.typeFront || this.searchParams?.type;
        },

        createQueryBuilderFilter() {
            return {
                id: this.name,
                label: this.getLanguage().translate(this.name, 'fields', this.model.urlRoot),
                type: 'string',
                optgroup: this.getLanguage().translate('Fields'),
                operators: ['contains', 'not_contains', 'equal', 'not_equal', 'is_null', 'is_not_null'],
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
