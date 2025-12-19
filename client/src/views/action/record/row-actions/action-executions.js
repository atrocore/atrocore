/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/row-actions/action-executions', 'views/record/row-actions/relationship-no-unlink',
    Dep => Dep.extend({

        getActionList: function () {
            let list = Dep.prototype.getActionList.call(this);

            list.unshift({
                action: 'allLogs',
                label: this.translate('allLogs', 'labels', 'Action'),
                data: {
                    id: this.model.id,
                    name: this.model.get('name'),
                }
            });

            return list;
        },
    })
);


