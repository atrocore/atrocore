/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/record/row-actions/for-relationship-type', 'views/record/row-actions/relationship', Dep => {

    return Dep.extend({

        getActionList() {
            let list = [];

            if (this.model.get('isInherited') === false) {
                list.push({
                    action: 'inheritRelationship',
                    label: 'setAsInherited',
                    data: {
                        entity: this.model.name,
                        id: this.model.id
                    }
                });
            }

            list.push({
                action: 'quickView',
                label: 'View',
                data: {
                    id: this.model.id
                },
                link: '#' + this.model.name + '/view/' + this.model.id
            });

            if (this.options.acl.edit) {
                list.push(
                    {
                        action: 'quickEdit',
                        label: 'Edit',
                        data: {
                            id: this.model.id
                        },
                        link: '#' + this.model.name + '/edit/' + this.model.id
                    }
                );
            }

            if (this.options.acl.delete) {
                list.push({
                    action: 'removeRelated',
                    label: 'Remove',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return list;
        }

    });
});
