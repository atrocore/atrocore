/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matched-record/fields/staging', 'views/fields/link', Dep => {

    return Dep.extend({

        setup() {
            this.options.foreignScope = this.model.get('stagingEntity');

            Dep.prototype.setup.call(this);
        },

    });
});