/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/navigation', 'views/modal',
    (Dep) => Dep.extend({

        template: 'layout-profile/modals/navigation',

        setup() {
            this.buttonList = [];
            this.model = this.options.model;
            this.field = this.options.field;
            this.header = this.getLanguage().translate(this.field, 'fields', 'LayoutProfile');
            Dep.prototype.setup.call(this);
        },

        afterRender() {
            if (window.layoutSvelteComponent) {
                try {
                    window.layoutSvelteComponent.$destroy()
                } catch (e) {
                }
            }

            this.$el.find('.modal-body').css('paddingTop', 0);

            if (!this.$el.find('.navigation').length) {
                return;
            }

            window.layoutSvelteComponent = new Svelte.Navigation({
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
                        },
                        onEditItem: (item, callback) => {
                            this.createView('addGroupModal', 'views/layout-profile/modals/edit-tab-group', {
                                attributes: item
                            }, view => {
                                view.render();
                                this.listenToOnce(view, 'after:save', data => {
                                    if (callback) {
                                        callback({
                                            ...item,
                                            name: item.id,
                                            label: data.name,
                                            color: data.color,
                                            iconClass: data.iconClass,
                                        });
                                    }
                                    view.close();
                                });
                            })
                        }
                    },
                    inModal: true
                }
            })
        }
    })
);
