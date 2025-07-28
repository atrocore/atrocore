/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/fields/attribute-panel', 'views/fields/link',
    Dep => Dep.extend({

        selectBoolFilterList: ['onlyForEntity'],

        boolFilterData: {
            onlyForEntity() {
                return this.model.get('entityId') || null;
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityId', () => {
                if (this.model.isNew()) {
                    $.each((this.getConfig().get('referenceData')?.AttributePanel || {}), (id, panel) => {
                        if (panel.entityId === this.model.get('entityId') && panel.default) {
                            this.model.set('attributePanelId', panel.id);
                            this.model.set('attributePanelName', panel[this.getLocalizedFieldData('AttributePanel', 'name')[0]]);
                        }
                    });
                }
            });

        },

    })
);
