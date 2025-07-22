/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/attribute/record/detail-bottom', 'views/record/detail-bottom',
    Dep => Dep.extend({

        createPanelViews() {
            this.panelList.forEach(p => {
                if (p.name === 'extensibleEnumOptions') {
                    this.getModelFactory().create('ExtensibleEnum', model => {
                        model.set('id', this.model.get('extensibleEnumId') || 'no-such-id');
                        model.attributeModel = this.model;
                        p = _.extend(p, this.getMetadata().get(['clientDefs', 'ExtensibleEnum', 'relationshipPanels', 'extensibleEnumOptions']));
                        p.model = model;
                        p.label = this.translate('extensibleEnumOptions', 'fields', 'ExtensibleEnum');
                        this.createPanelView(p);
                    });
                } else {
                    this.createPanelView(p);
                }
            });
        },

    })
);