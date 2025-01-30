/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/fields/navigation', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        editTemplate: 'fields/varchar/detail',

        detailTemplate: 'fields/varchar/detail',

        data: function () {
            var data = Dep.prototype.data.call(this);
            data['value'] = '...'
        },

        inlineEdit: function () {
          console.log('iciiiii');
        },
    });
});

