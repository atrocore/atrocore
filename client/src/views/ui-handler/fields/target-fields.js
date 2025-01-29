/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/ui-handler/fields/target-fields', ['views/fields/entity-fields', 'views/fields/entity-field'], (Dep, Field) => {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change:type', () => {
                this.prepareEnumOptions();
                this.reRender();
            });
        },

        prepareEnumOptions() {
            this.params.options = [];
            this.translatedOptions = {};

            $.each((Field.prototype.getEntityFields.call(this) || []), field => {
                if (this.model.get('type') === 'ui_disable_options') {
                    const type = this.getMetadata().get(['entityDefs', this.getEntityType(), 'fields', field, 'type'])
                    if (!['enum', 'multiEnum','groupedEnum', 'extensibleEnum', 'extensibleMultiEnum'].includes(type)) {
                        return
                    }
                }
                if (this.model.get('type') === 'ui_update_by_ai') {
                    const type = this.getMetadata().get(['entityDefs', this.getEntityType(), 'fields', field, 'type'])
                    if (!['varchar', 'text', 'wysiwyg'].includes(type) || field === 'id') {
                        return
                    }
                }
                this.params.options.push(field);
                this.translatedOptions[field] = this.translate(field, 'fields', this.getEntityType());
            });
        },

        afterRender() {
            if (['ui_disable_options', 'ui_update_by_ai'].includes(this.model.get('type'))) {
                this.options.maxItems = 1
            } else {
                this.options.maxItems = null
            }

            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'list') {
                if (['ui_required', 'ui_visible', 'ui_read_only', 'ui_disable_options', 'ui_update_by_ai'].includes(this.model.get('type'))) {
                    this.$el.parent().show();
                } else {
                    this.$el.parent().hide();
                }
            }
        },

    });
});

