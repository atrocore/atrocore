/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/modified-extended-enabled', 'views/fields/bool', Dep => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:type', () => {
                this.reRender();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            let shouldHide = true;
            let scope = this.model.get('entityId');
            let field = this.model.get('code');
            if (
                this.model.get('type') === 'linkMultiple'
                && this.model.get('notStorable') !== true
                && this.model.get('disabled') !== true
            ) {
                  shouldHide = false;
            }

            if (
                this.model.get('type') === 'linkMultiple'
                && this.model.get('notStorable') !== true
                && this.model.get('disabled') !== true
                && (this.getMetadata().get(['scopes', scope, 'modifiedExtendedLinks']) || []).includes(field)
            ) {
               shouldHide = false;
            }

            if(shouldHide) {
                this.hide();
            }
        }
    });
});
