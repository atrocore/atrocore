/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/fields/extensible-multi-enum-dropdown', 'views/fields/link-multiple-dropdown', function (Dep) {
    return Dep.extend({
        setup: function () {
            this.idsName = this.name;
            this.namesName = this.name + 'Names';
            this.foreignScope = 'ExtensibleEnumOption';

            Dep.prototype.setup.call(this);
        },

        prepareOptionsList: function () {
            this.params.options = [];
            this.translatedOptions = {};
            this.params.optionColors = [];

            this.getListOptionsData(this.getExtensibleEnumId()).forEach(option => {
                if (option.id) {
                    this.params.options.push(option.id);
                    this.translatedOptions[option.id] = option.name || option.id;
                    this.params.optionColors.push(option.color || null);
                }
            });
        },

        getExtensibleEnumId() {
            let extensibleEnumId = this.getMetadata().get(['entityDefs', this.model.name, 'fields', this.name, 'extensibleEnumId']);
            if (this.params.extensibleEnumId) {
                extensibleEnumId = this.params.extensibleEnumId;
            }

            return extensibleEnumId;
        }
    });
});