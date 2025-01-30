/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/layouts/modals/edit', 'views/modal',
    (Dep) => Dep.extend({

        template: 'admin/layout-profile/modals/navigation',

        setup() {
            this.buttonList = [];

            this.header = this.getLanguage().translate('layoutManagement', 'labels', 'LayoutManager');

        },

        afterRender() {
            console.log('iiiiiiii')
            window.layoutSvelteComponent = new Svelte.Navigation({
                target: this.$el.get(0),
                inModal: true
            })
        }
    })
);
