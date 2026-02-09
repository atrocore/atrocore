/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/is-uninherited-field', 'views/fields/bool', Dep => {

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

            if (
                !['linkMultiple', 'autoincrement'].includes(this.model.get('type'))
                && !(this.getMetadata().get('app.nonInheritedFields') || []).includes(this.model.get('code'))
                && !(this.getMetadata().get(['scopes', this.model.get('entityId'), 'mandatoryUnInheritedFields']) || []).includes(this.model.get('code'))
                && this.model.get('notStorable') !== true
                && this.model.get('disabled') !== true
                && this.getMetadata().get(['scopes', this.model.get('entityId'), 'type']) === 'Hierarchy'
            ) {
                shouldHide = false;
            }

            if (shouldHide) {
                this.hide();
            }
        }
    });
});

