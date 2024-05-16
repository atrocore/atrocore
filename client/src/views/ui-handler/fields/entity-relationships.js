/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/entity-relationships', 'views/fields/entity-relationships', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        prepareEnumOptions() {
            Dep.prototype.prepareEnumOptions.call(this);

            // push attribute tabs
            if (this.getEntityType() === 'Product') {
                (this.getMetadata().get(['clientDefs', 'Product', 'bottomPanels', 'detail']) || []).forEach(item => {
                    if (item.tabId) {
                        this.params.options.push(item.name);
                        this.translatedOptions[item.name] = item.label;
                    }
                })
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                if (this.model.get('type') === 'ui_visible') {
                    this.$el.parent().show();
                } else {
                    this.$el.parent().hide();
                }
            }
        },

    });
});

