/*
 * This file is part of premium software, which is NOT free.
 * Copyright (c) AtroCore GmbH.
 *
 * This Software is the property of AtroCore GmbH and is
 * protected by copyright law - it is NOT Freeware and can be used only in one
 * project under a proprietary license, which is delivered along with this program.
 * If not, see <https://atropim.com/eula> or <https://atrodam.com/eula>.
 *
 * This Software is distributed as is, with LIMITED WARRANTY AND LIABILITY.
 * Any unauthorised use of this Software without a valid license is
 * a violation of the License Agreement.
 *
 * According to the terms of the license you shall not resell, sublicense,
 * rent, lease, distribute or otherwise transfer rights or usage of this
 * Software or its derivatives. You may modify the code of this Software
 * for your own needs, if source code is provided.
 */

Espo.define('views/admin/layouts/modals/edit', 'views/modal',
    Dep => Dep.extend({

        template: 'admin/layouts/modals/edit',
        events: {
            'layout-updated': (event) => {
                console.log('event')
            }
        },

        setup() {
            this.scope = this.options.scope;

            this.buttonList = [];

            this.header = this.getLanguage().translate('customizeLayout', 'labels');
        },

        afterRender() {
            if (window.layoutSvelteComponent) {
                try {
                    window.layoutSvelteComponent.$destroy()
                } catch (e) {

                }
            }
            window.layoutSvelteComponent = new Svelte.LayoutComponent({
                target: document.querySelector('#layout-content'),
                props: {
                    params: {
                        type: this.options.type,
                        scope: this.options.scope,
                        layoutProfileId: 'custom',
                    }
                }
            });

            window.addEventListener('layoutUpdated', this.layoutUpdated.bind(this))
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
        },

        beforeRemove: function () {
            // Detach the event listener before the view is destroyed
            document.removeEventListener('layoutUpdated', this.layoutUpdated.bind(this));

            Dep.prototype.beforeRemove.call(this);
        }
    })
);
