/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/record/list', 'views/record/list',
    Dep => Dep.extend({
        massActionList: ['remove', 'merge', 'massUpdate', 'export', 'download'],

        checkAllResultMassActionList: ['remove', 'massUpdate', 'export', 'download'],

        massActionDownload: function () {
            Espo.Ui.notify(this.translate('loading', 'messages'));
            var where = this.collection.getWhere();
            var allResultIsChecked = this.allResultIsChecked;
            if (!allResultIsChecked) {
                if (this.checkedList.length < 2) {
                    this.notify('Select 2 or more records', 'error')
                    return;
                }
                where = [
                    {
                        attribute: 'id',
                        type: 'in',
                        value: this.checkedList
                    }
                ]
            }

            this.ajaxPostRequest("File/action/massDownload", {
                where: where
            }).success(response => {
                this.notify(this.translate('jobAdded', 'messages'), 'success')
            })
        },
    })
);