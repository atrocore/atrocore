/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/favorites', 'views/modal',
    (Dep) => Dep.extend({

        template: 'layout-profile/modals/navigation',

        fullHeight: true,

        setup() {
            this.buttonList = [];
            this.model = this.options.model;
            this.field = this.options.field;
            this.header = this.getLanguage().translate(this.field, 'fields', 'LayoutProfile');
            Dep.prototype.setup.call(this);
        },

        afterRender() {
            if (window.layoutSvelteFavorites) {
                try {
                    window.layoutSvelteFavorites.$destroy()
                } catch (e) {
                }
            }

            this.$el.find('.modal-body').css('paddingTop', 0);

            if (!this.$el.find('.navigation').length) {
                return;
            }

            window.layoutSvelteFavorites = new Svelte.Favorites({
                target: this.$el.find('.navigation').get(0),
                props: {
                    params: {
                        list: this.model.get(this.field),
                        onSaved: (favorites) => {
                            let attributes = {};
                            attributes[this.field] = favorites['navigation'];

                            this.notify('Loading...');
                            this.model.save(attributes, {
                                patch: true
                            }).then(() => {
                                this.close();
                                this.notify('Done', 'success');

                                if (this.options.afterSave) {
                                    this.options.afterSave();
                                }
                            });
                        },
                    },
                    inModal: true
                }
            })
        }
    })
);
