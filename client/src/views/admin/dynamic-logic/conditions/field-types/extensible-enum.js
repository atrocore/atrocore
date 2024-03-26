/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/dynamic-logic/conditions/field-types/extensible-enum', 'views/admin/dynamic-logic/conditions/field-types/base', function (Dep) {

    return Dep.extend({

        fetch: function () {
            const valueView = this.getView('value');

            const item = {
                type: this.type,
                attribute: this.field,
                data: {
                    field: this.field
                }
            };

            if (valueView) {
                valueView.fetchToModel();
                item.value = this.model.get(this.field);

                var values = {};
                values[this.field + 'Name'] = this.model.get(this.field + 'Name');
                item.data.values = values;
            }

            return item;
        }

    });

});