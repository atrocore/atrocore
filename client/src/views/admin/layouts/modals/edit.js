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

            this.header = this.getLanguage().translate('layoutManagement', 'labels', 'LayoutManager');

            let allowSwitch = true
            if (this.options.allowSwitch === false) {
                allowSwitch = false
            }

            this.getModelFactory().create('Layout', (model) => {
                this.model = model;
                model.set('id', '1')
                model.set('layoutProfileId', this.options.layoutProfileId)
                model.set('layoutProfileName', this.options.layoutProfileName)

                // create field views
                this.createView('layoutProfile', 'views/layout/fields/layout-profile', {
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
                    prohibitedEmptyValue: true,
                    withLayoutsParams: {
                        viewType: this.options.type,
                        scope: this.options.scope,
                        relatedScope: this.options.relatedScope
                    }
                })

                this.listenTo(this.model, 'change:layoutProfileId', () => {
                    if (this.model.get('layoutProfileId')) {
                        this.afterRender()
                    }
                })
            })
        },

        afterRender() {
            LayoutUtils.renderComponent.call(this, {
                type: this.options.type,
                scope: this.options.scope,
                relatedScope: this.options.relatedScope,
                layoutProfileId: this.model.get('layoutProfileId'),
                editable: true,
                onUpdate: this.layoutUpdated.bind(this),
                getActiveLayoutProfileId: () => this.model.get('layoutProfileId'),
                inModal: true
            })
        },

        layoutUpdated(reset) {
            this.layoutIsUpdated = true
            this._helper.layoutManager.savePreference(this.options.scope, this.options.type, this.options.relatedScope, reset ? null : this.model.get('layoutProfileId'), () => {
                this.actionClose()
            })
        },

        onDialogClose: function () {
            if (!this.isBeingRendered()) {
                this.trigger('close', {
                    layoutIsUpdated: this.layoutIsUpdated
                });
                this.remove();
            }
        }
    })
);
