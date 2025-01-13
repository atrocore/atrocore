/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/sort-by', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            const scope = this.model.get('code');
            const fieldDefs = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};

            this.params.options = Object.keys(fieldDefs).filter(function (item) {
                if (fieldDefs[item].notStorable || ['link', 'linkMultiple'].includes(fieldDefs[item].type)) {
                    return false;
                }
                return true;
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
            }.bind(this));

            this.translatedOptions = {'': ''};
            this.params.options.forEach(option => {
                this.translatedOptions[option] = this.translate(option, 'fields', scope);
            })
        },

    });
});
