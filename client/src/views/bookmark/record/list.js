/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/bookmark/record/list', 'views/record/list-expanded', function (Dep) {

    return Dep.extend({

        headerDisabled: true,

        massActionsDisabled: true,

        rowActionsView: 'views/record/row-actions/default',

        showCount: false,

        showMore: false,

        buttonsDisabled: true,

        checkboxes: false
    });
});
