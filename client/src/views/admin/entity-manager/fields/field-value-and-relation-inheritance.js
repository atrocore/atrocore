/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/field-value-and-relation-inheritance', 'views/admin/entity-manager/fields/bool-for-type', function (Dep) {

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const disableHierarchy = this.$el.parent().parent().parent().find('.field[data-name="disableHierarchy"] input');

            if (disableHierarchy.length) {
                this.checkFieldVisibility(this.model.get('disableHierarchy'));

                disableHierarchy.change(() => {
                    this.checkFieldVisibility(disableHierarchy.is(':checked'));
                });
            }
        },

        checkFieldVisibility(disableHierarchy) {
            let el = this.$el.find('input');

            if (el.length) {
                if (this.model.get('type') === 'Hierarchy' && disableHierarchy === true) {
                    el.prop('disabled', true);
                    el.prop('checked', false);
                } else {
                    el.prop('disabled', false);
                }

                el.trigger('change');
            }
        }
    });
});
