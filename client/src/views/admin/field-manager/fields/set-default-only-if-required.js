/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/set-default-only-if-required', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:default change:required', () => {
                if (this.model.get('setDefaultOnlyIfRequired') && (!this.model.get('default')|| this.model.get('required'))) {
                    this.model.set('setDefaultOnlyIfRequired', null)
                }
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.parent().hide();
            if (this.model.get('default') && !this.model.get('required')) {
                this.$el.parent().show();
            }
        },

    });

});
