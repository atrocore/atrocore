/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user/user-profile', 'views/detail', Dep => {

    return Dep.extend({

        scope: 'User',

        recordView: 'views/user/record/user-profile-detail',

        getRecordViewName() {
            return this.recordView;
        },

    });
});

