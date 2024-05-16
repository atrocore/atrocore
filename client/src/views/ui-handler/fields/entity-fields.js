/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/entity-fields', 'views/fields/entity-fields', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:triggerAction', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {

                // console.log(this.model.get('triggerAction').includes(''))

                // if (this.model.get('type') === 'ui_visible') {
                //     this.$el.parent().show();
                // } else {
                //     this.$el.parent().hide();
                // }
            }
        },

    });
});

