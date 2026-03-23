/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/page', ['view'], function (Dep) {

    return Dep.extend({

        template: 'admin/page',

        setup() {
            this.once('after:render', () => {
                this.renderSvelteComponent();

                new Svelte.TreePanel({
                    target: $(`${this.options.el} .content-wrapper`).get(0),
                    anchor: $(`${this.options.el} .content-wrapper .tree-panel-anchor`).get(0),
                    props: {
                        scope: 'Settings',
                        model: this.model,
                        mode: 'detail',
                        isAdminPage: true,
                        callbacks: {}
                    }
                });

                const page = this.options.page;
                if (page) {
                    this.logToNavigationHistory(page);
                }
            });
        },

        renderSvelteComponent() {
            const page = this.options.page;
            if (!page) return;

            const componentName = page.charAt(0).toUpperCase() + page.slice(1) + 'Component';
            const component = Svelte[componentName];

            if (!component) {
                console.error(`Svelte component not found: ${componentName}`);
                return;
            }

            new component({
                target: this.$el.find('#admin-page-content').get(0),
            });
        },

        updatePageTitle() {
            const page = this.options.page;
            if (page) {
                this.setPageTitle(this.getLanguage().translate(page, 'labels', 'Admin'));
            }
        }
    });
});
