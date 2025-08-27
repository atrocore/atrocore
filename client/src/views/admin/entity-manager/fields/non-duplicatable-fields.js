/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/admin/entity-manager/fields/non-duplicatable-fields', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        setupOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            const scope = this.model.get('code');

            $.each((this.getMetadata().get(['entityDefs', scope, 'fields']) || {}), (field, fieldDefs) => {
                if (
                    !['id', 'createdAt', 'modifiedAt', 'createdBy', 'modifiedBy'].includes(field)
                    && fieldDefs.type !== 'linkMultiple'
                    && fieldDefs.notStorable !== true
                    && fieldDefs.disabled !== true
                    && fieldDefs.emHidden !== true
                ) {
                    this.params.options.push(field);
                    this.translatedOptions[field] = this.translate(field, 'fields', scope);
                }
            });
        }
    });
});