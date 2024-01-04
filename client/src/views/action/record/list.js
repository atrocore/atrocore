/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/action/record/list', 'views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.massActionList.push('execute');
            this.checkAllResultMassActionList.push('execute');
        },

        massActionExecute() {
            let where = [];
            if (this.allResultIsChecked) {
                where = this.collection.getWhere();
            } else {
                where = [{
                    type: "in",
                    attribute: "id",
                    value: this.checkedList
                }];
            }

            this.confirm(this.translate('executeNow', 'messages', 'Action'), () => {
                this.notify('Please wait...');
                this.ajaxPostRequest('Action/action/executeNow', {where: where}).then(response => {
                    if (response) {
                        this.notify('Done', 'success');
                    }
                });
            });
        },
    })
);