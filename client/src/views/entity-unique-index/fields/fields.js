/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-unique-index/fields/fields', 'views/fields/multi-enum', Dep => {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.prepareOptions();

            this.listenTo(this.model, 'change:entityId', () => {
                this.prepareOptions();
                this.reRender();
            });
        },

        prepareOptions: function () {
            const scope = this.model.get('entityId');

            this.params.options = [];
            this.translatedOptions = {};

            if (!scope) {
                return;
            }

            const fieldDefs = this.getMetadata().get(['entityDefs', scope, 'fields']) || {};

            this.params.options = Object.keys(fieldDefs).filter(field => this.isFieldAvailable(scope, field, fieldDefs[field]));

            this.params.options.forEach(field => {
                this.translatedOptions[field] = this.translate(field, 'fields', scope);
            });
        },

        /**
         * Only simple field types having their own column in the table can be a part of the unique index.
         */
        isFieldAvailable: function (scope, field, fieldDefs) {
            if (!fieldDefs || !fieldDefs.type) {
                return false;
            }

            // the primary key is unique on its own, such an index makes no sense
            if (['id', 'deleted'].includes(field)) {
                return false;
            }

            if (fieldDefs.notStorable || fieldDefs.disabled || fieldDefs.emHidden) {
                return false;
            }

            return !!this.getMetadata().get(['fields', fieldDefs.type, 'uniqueIndexAllowed']);
        }

    });
});
