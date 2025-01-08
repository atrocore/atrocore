/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/text-filter-fields', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            const scope = this.model.get('code');
            const fieldDefs = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};

            this.params.options = Object.keys(fieldDefs).filter(function (item) {
                var fieldType = fieldDefs[item].type;
                if (!this.getMetadata().get(['fields', fieldType, 'textFilter'])) return false
                if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'disabled'])) {
                    return false;
                }
                if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'notStorable'])) {
                    return false;
                }
                if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'emHidden'])) {
                    return false;
                }
                if (this.getMetadata().get(['entityDefs', scope, 'fields', item, 'textFilterDisabled'])) {
                    return false;
                }
                return true;
            }, this);

            this.translatedOptions = {'': ''};
            this.params.options.forEach(option => {
                this.translatedOptions[option] = this.translate(option, 'fields', scope);
            })
        },

    });
});
