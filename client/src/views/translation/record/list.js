/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/translation/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        massActionList: ['massUpdate', 'export'],

        checkAllResultMassActionList: ['massUpdate', 'export'],

    });
});

