/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/detail-header', 'views/header', function (Dep) {

    return Dep.extend({

        template: 'detail-header',

        data() {
            const record = this.getParentView().getView('record');
            return _.extend(Dep.prototype.data.call(this), {
                buttonList: record.buttonList,
                buttonEditList: record.buttonEditList,
                dropdownItemList: record.isDropdownItemListEmpty() ? [] : record.dropdownItemList,
                dropdownEditItemList: record.dropdownEditItemList,
                dropdownItemListEmpty: record.isDropdownItemListEmpty(),
                buttonsDisabled: record.buttonsDisabled,
                hasButtons: record.buttonList.length > 0 || record.dropdownItemList.length > 0 || record.additionalButtons.length > 0,
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            new Svelte.DetailHeader({
                target: document.querySelector('.detail-page-header'),
            })
        }

    });
});

