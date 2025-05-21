/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/field-manager/fields/extensible-multi-enum-default', 'views/admin/field-manager/fields/link-multiple-default', Dep => {

    return Dep.extend({

        selectBoolFilterList: ['defaultOption', 'onlyAllowedOptions'],

        boolFilterData: {
            defaultOption() {
                return {
                    extensibleEnumId: this.model.get('extensibleEnumId') ?? this.model.defs.fields[this.name]?.extensibleEnumId
                };
            },
            onlyAllowedOptions() {
                return this.model.get('allowedOptions') || null
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:extensibleEnumId', () => {
                this.model.set(this.idName, null);
                this.model.set(this.nameName, null);
            });
        },

        getForeignScope() {
            return 'ExtensibleEnumOption';
        },

    });
});