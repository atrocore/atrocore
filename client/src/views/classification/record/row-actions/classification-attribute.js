/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification/record/row-actions/classification-attribute', 'views/record/row-actions/relationship-view-and-edit',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);

            list = list.filter(item => item.action !== 'inheritRelated');

            if (this.options.acl.delete) {
                list.push({
                    action: 'unlinkRelatedAttribute',
                    label: this.translate('unlinkRelatedAttribute', 'labels', 'ClassificationAttribute'),
                    data: {
                        id: this.model.id
                    }
                });

                list.push({
                    action: 'cascadeUnlinkRelatedAttribute',
                    label: this.translate('cascadeUnlinkRelatedAttribute', 'labels', 'ClassificationAttribute'),
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        },
    })
);


