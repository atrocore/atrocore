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
                this.listenTo(this.model, `change:matchingId`, () => {
                    this.model.set(this.name, null);
                    this.prepareListOptions();
                    this.reRender();
                });
            })

            this.listenTo(this.model, `change:${this.name}`, () => {
                if (this.model.isNew() && this.model.get('entityField') && this.model.get('entityField') !== '_addAttribute') {
                    let optionName = this.getLanguage().translateOption(this.model.get('type'), 'type', 'QualityCheckRule');
                    let fieldName = this.translate(this.model.get('entityField'), 'fields', this.model.get('qualityCheckEntity'));

                    let channelId = this.getMetadata().get(`entityDefs.${this.model.get('qualityCheckEntity')}.fields.${this.model.get('entityField')}.channelId`);
                    if (channelId) {
                        this.ajaxGetRequest(`Channel/${channelId}`, null, {async: false}).success(channel => {
                            fieldName += ` / ${channel.name}`;
                        });
                    }

                    this.model.set('name', `${optionName}: ${fieldName}`);
                }

                this.model.set('attributeId', this.getMetadata().get(`entityDefs.${this.model.get('qualityCheckEntity')}.fields.${this.model.get(this.name)}.attributeId`) || null);

                if (this.model.get(this.name) === '_addAttribute') {
                    this.actionSelectAttribute();
                }
            });
        },

        prepareListOptions() {
            this.translatedOptions = {};
            this.originalOptionList = this.params.options = [];

            if (this.model.get('matchingId') === null) {
                return;
            }

            let entityName = null;
            if (this.name === 'targetField') {
                $.each((this.getConfig().get('referenceData')?.Matching || {}), (code, item) => {
                    if (item.id === this.model.get('matchingId')) {
                        entityName = item.targetEntity;
                    }
                })
            } else if (this.name === 'sourceField') {
                $.each((this.getConfig().get('referenceData')?.Matching || {}), (code, item) => {
                    if (item.id === this.model.get('matchingId')) {
                        entityName = item.sourceEntity;
                    }
                })
            }

            if (!entityName) {
                return;
            }

            let availableTypes = [
                // "array",
                // "bool",
                // "date",
                // "datetime",
                // "enum",
                // "extensibleEnum",
                // "extensibleMultiEnum",
                // "file",
                // "float",
                // "int",
                // "link",
                "markdown",
                // "measure",
                // "multiEnum",
                "password",
                "text",
                "url",
                "varchar",
                "wysiwyg"
            ];

            $.each(this.getMetadata().get(['entityDefs', entityName, 'fields'], {}), (field, fieldDefs) => {
                if (!fieldDefs.disabled && availableTypes.includes(fieldDefs.type) && !fieldDefs.importDisabled) {
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