/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/navigation', 'views/layout-profile/modals/navigation',
    (Dep) => Dep.extend({

        afterRender() {
            if (window.layoutSvelteQuickCreate) {
                try {
                    window.layoutSvelteQuickCreate.$destroy()
                } catch (e) {
                }
            }

            this.$el.find('.modal-body').css('paddingTop', 0);

            if(!this.$el.find('.navigation').length) {
                return;
            }

            window.layoutSvelteQuickCreate = new Svelte.QuickCreate({
                target: this.$el.find('.navigation').get(0),
                props: {
                    params: {
                        navigation: this.model.get(this.field),
                        onSaved: (navigation) => {
                            let attributes = {};
                            attributes[this.field] = navigation;
                            this.close();
                            this.notify('Loading...');
                            this.model.save(attributes, {
                                patch: true
                            }).then(() => {
                                this.notify('Done', 'success')
                            });
                        }
                    },
                    inModal: true
                }
            })
        }
    })
);
