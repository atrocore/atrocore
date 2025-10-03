/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matching-rule/fields/entity-field', 'views/fields/enum', Dep => {

    return Dep.extend({

        setup() {
            this.prepareListOptions();
            Dep.prototype.setup.call(this);
            this.onModelReady(() => {
                this.listenTo(this.model, 'change:type change:matchingId', () => {
                    this.model.set(this.name, null);
                    this.prepareListOptions();
                    this.reRender();
                });
            })
        },

        prepareListOptions() {
            this.translatedOptions = {};
            this.originalOptionList = this.params.options = [];

            if (!['like'].includes(this.model.get('type')) || this.model.get('matchingId') === null) {
                return;
            }

            let entityName = null;

            $.each((this.getConfig().get('referenceData')?.Matching || {}), (code, item) => {
                if (item.id === this.model.get('matchingId')) {
                    if (item.type === 'duplicate') {
                        entityName = item.entity;
                    } else if (item.type === 'staging') {
                        if (this.name === 'targetField') {
                            entityName = item.masterEntity;
                        } else if (this.name === 'sourceField') {
                            entityName = item.stageEntity;
                        }
                    }
                }
            })

            if (!entityName) {
                return;
            }

            let availableTypes = this.getMetadata().get(`app.matchingRules.${this.model.get('type')}.fieldTypes`) || [];

            $.each(this.getMetadata().get(['entityDefs', entityName, 'fields'], {}), (field, fieldDefs) => {
                if (
                    !fieldDefs.disabled 
                    && availableTypes.includes(fieldDefs.type) 
                    && !fieldDefs.importDisabled 
                    && !fieldDefs.unitField 
                    && !fieldDefs.notStorable
                ) {
                    this.translatedOptions[field] = this.translate(field, 'fields', entityName);
                }
            })

            this.params.options.sort((a, b) => {
                return this.translatedOptions[a].localeCompare(this.translatedOptions[b])
            });

            this.params.options = Object.keys(this.translatedOptions);
            this.originalOptionList = this.params.options;
        },

    });
});