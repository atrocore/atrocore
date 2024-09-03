/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/layouts/modals/edit', ['views/modal', 'views/admin/layouts/layout-utils'],
    (Dep, LayoutUtils) => Dep.extend({

        template: 'admin/layouts/modals/edit',

        setup() {
            this.scope = this.options.scope;

            this.buttonList = [];

            this.header = this.getLanguage().translate('customizeLayout', 'labels');
        },

        afterRender() {
            LayoutUtils.renderComponent.call(this, {
                type: this.options.type,
                scope: this.options.scope,
                layoutProfileId: this.options.layoutProfileId ?? 'custom',
                editable: true,
                onUpdate: this.layoutUpdated.bind(this)
            })
        },

        layoutUpdated(event) {
            this.layoutIsUpdated = true
            this.actionClose()
        },

        onDialogClose: function () {
            if (!this.isBeingRendered()) {
                this.trigger('close', {layoutIsUpdated: this.layoutIsUpdated});
                this.remove();
            }
        }
    })
);
