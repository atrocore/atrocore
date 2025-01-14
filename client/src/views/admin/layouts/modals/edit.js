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

        getLayoutProfiles() {
            const scope = 'LayoutProfile'

            let key = 'link_' + scope;

            if (!Espo[key]) {
                Espo[key] = [];
                this.ajaxGetRequest(scope, {
                    offset: 0,
                    maxSize: 100
                }, {async: false}).then(res => {
                    if (res.list) {
                        Espo[key] = res.list;
                    }
                });
            }

            return Espo[key];
        },

        afterRender() {
            this.getLayoutProfiles()

            let allowSwitch = true
            if (this.options.allowSwitch === false) {
                allowSwitch = false
            }
            LayoutUtils.renderComponent.call(this, {
                type: this.options.type,
                scope: this.options.scope,
                layoutProfileId: this.options.layoutProfileId ?? 'custom',
                editable: true,
                onUpdate: this.layoutUpdated.bind(this),
                allowSwitch: allowSwitch
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
