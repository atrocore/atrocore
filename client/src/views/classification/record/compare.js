/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/classification/record/compare', 'views/record/compare', function (Dep) {

    return Dep.extend({

        isComparableLink(link) {
            if(['classificationAttributes'].includes(link)) {
                return true;
            }

            return Dep.prototype.isComparableLink.call(this, link);
        }
    });
});