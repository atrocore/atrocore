/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/non-duplicatable-fields', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        setupOptions() {
            const scope = this.model.get('code');

            this.translatedOptions = {};
            $.each((this.getMetadata().get(['entityDefs', scope, 'fields']) || {}), (field, fieldDefs) => {
                if (
                    !['id', 'createdAt', 'modifiedAt', 'createdBy', 'modifiedBy'].includes(field)
                    && fieldDefs.type !== 'linkMultiple'
                    && fieldDefs.notStorable !== true
                    && fieldDefs.disabled !== true
                    && fieldDefs.emHidden !== true
                ) {
                    this.translatedOptions[field] = this.translate(field, 'fields', scope);
                }
            });

            this.translatedOptions = Object.fromEntries(Object.entries(this.translatedOptions).sort(([keyA], [keyB]) => keyA.localeCompare(keyB)));
            this.params.options = Object.keys(this.translatedOptions);

            this.originalOptionList = this.params.options;
        }
    });
});