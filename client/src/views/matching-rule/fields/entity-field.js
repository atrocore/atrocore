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

        findMatchingId(matchingRuleSetId) {
            let res = null;

            (this.getConfig().get('matchingRules') || []).forEach(item => {
                if (item.id === matchingRuleSetId) {
                    if (item.matchingRuleSetId) {
                        res = this.findMatchingId(item.matchingRuleSetId);
                    } else {
                        res = item.matchingId;
                    }
                }
            })

            return res;
        },

        prepareListOptions() {
            this.translatedOptions = {};
            this.originalOptionList = this.params.options = [];

            if (!['equal', 'like', 'contains'].includes(this.model.get('type'))) {
                return;
            }

            let entityName = null;
            let matchingId = this.model.get('matchingId');
            if (this.model.get('matchingRuleSetId')) {
                matchingId = this.findMatchingId(this.model.get('matchingRuleSetId'));
            }

            (this.getConfig().get('matchings') || []).forEach(item => {
                if (item.id === matchingId) {
                    entityName = item.masterEntity;
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