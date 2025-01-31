/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/dashboard-layout', 'views/layout-profile/modals/navigation',
    (Dep) => Dep.extend({

        afterRender() {
            if (window.layoutSvelteDashboardLayout) {
                try {
                    window.layoutSvelteDashboardLayout.$destroy()
                } catch (e) {
                }
            }

            this.$el.find('.modal-body').css('paddingTop', 0);

            if(!this.$el.find('.navigation').length) {
                return;
            }

            window.layoutSvelteDashboardLayout = new Svelte.DashboardLayout({
                target: this.$el.find('.navigation').get(0),
                props: {
                    params: {
                        layout: this.model.get(this.field),
                        onSaved: (layout) => {
                            let attributes = {};
                            attributes[this.field] = layout;
                            this.close();
                            this.notify('Loading...');
                            this.model.save(attributes, {
                                patch: true
                            }).then(() => {
                                this.notify('Done', 'success')
                            });
                        }
                    }
                }
            })
        }
    })
);
