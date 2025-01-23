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

            this.buttonList = ["save"];

            this.header = this.getLanguage().translate('layoutManagement', 'labels');

            let allowSwitch = true
            if (this.options.allowSwitch === false) {
                allowSwitch = false
            }

            this.getModelFactory().create('Layout', (model) => {
                this.model = model;
                model.set('id', '1')
                model.set('layoutProfileId', this.layoutProfileId)

                // create field views
                this.createView('layoutProfile', 'views/fields/link', {
                    name: 'layoutProfile',
                    el: `${this.options.el} .field[data-name="layoutProfile"]`,
                    model: this.model,
                    scope: 'Layout',
                    defs: {
                        name: 'layoutProfile',
                    },
                    readOnly: !allowSwitch,
                    mode: 'edit',
                    inlineEditDisabled: true,
                    prohibitedEmptyValue: true
                })
            })
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
            LayoutUtils.renderComponent.call(this, {
                type: this.options.type,
                scope: this.options.scope,
                relatedScope: this.options.relatedScope,
                layoutProfileId: this.options.layoutProfileId,
                editable: true,
                onUpdate: this.layoutUpdated.bind(this),
                layoutProfiles: this.getLayoutProfiles()
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
