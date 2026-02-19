/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/entity-field-type', 'views/fields/enum',
    Dep => Dep.extend({

        setupOptions() {
            this.reloadOptions()

            this.listenTo(this.model, 'change:entityType', () => {
                this.reloadOptions()
                this.model.set('entityField', this.params.options.includes('name') ? 'name' : this.params.options[0] ?? "")
            })
        },

        reloadOptions(entityType = null) {
            this.params.options = [];
            this.translatedOptions = {};

            entityType = entityType ?? this.model.get('entityType')

            const foreign = this.getMetadata().get(`entityDefs.${this.model.scope}.links.${this.model.get('entityType')}.entity`);
            const entityFields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};

            $.each(entityFields, (name, data) => {
                if (data.notStorable === true || data.type === 'link') {
                    return
                }
                if (data.type === 'varchar') {
                    this.params.options.push(name);
                    this.translatedOptions[name] = this.translate(name, 'fields', foreign);
                } else if (data.type === 'link' && ['ownerUser', 'assignedUser'].includes(name)) {
                    let linkEntity = this.getMetadata().get(['entityDefs', foreign, 'links', name, 'entity']);
                    if (linkEntity && this.getMetadata().get(['entityDefs', linkEntity, 'fields', 'name'])) {
                        this.params.options.push(name + 'Name');
                        this.translatedOptions[name + 'Name'] = this.translate('name', 'fields', 'Global') + ': ' + this.translate(name, 'fields', foreign);
                    }
                }
            });

            this.originalOptionList = this.params.options;
        }
    })
);
