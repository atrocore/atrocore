/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/company-logo', 'views/fields/file', function (Dep) {

    return Dep.extend({
        getSelectFilters: function () {
            return [{
                type: 'anyOf',
                attribute: 'typeId',
                // images and icons
                value: ['019c320b-77ba-73d3-8f1b-8346dce0f7bb', '019c320b-8c5f-7374-880c-ce48237046cb']
            }];
        }
    })
});
