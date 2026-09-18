/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/entity-unique-index/fields/name', 'views/fields/varchar', Dep => {

    return Dep.extend({

        /**
         * The last automatically generated value. While the user has not changed the name manually,
         * it is kept in sync with the selected fields.
         */
        generatedValue: null,

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew()) {
                return;
            }

            this.listenTo(this.model, 'change:fields', () => {
                this.generateName();
            });
        },

        generateName: function () {
            const current = this.model.get(this.name);

            // the user has typed his own name
            if (current && current !== this.generatedValue) {
                return;
            }

            const fields = this.model.get('fields') || [];

            let value = '';
            if (fields.length) {
                value = 'unique_' + fields.map(field => this.toUnderScore(field)).join('_');

                const maxLength = this.params.maxLength || this.model.getFieldParam(this.name, 'maxLength');
                if (maxLength && value.length > maxLength) {
                    value = value.substring(0, maxLength).replace(/_+$/, '');
                }
            }

            if (value === current) {
                return;
            }

            this.generatedValue = value;
            this.model.set(this.name, value, {silent: true});

            if (this.mode === 'edit' && this.$element && this.$element.length) {
                this.$element.val(value);
            }
        },

        toUnderScore: function (value) {
            return String(value).replace(/([a-z\d])([A-Z])/g, '$1_$2').toLowerCase();
        }

    });
});
