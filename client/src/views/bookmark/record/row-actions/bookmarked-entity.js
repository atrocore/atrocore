/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/bookmark/record/row-actions/bookmarked-entity', 'views/record/row-actions/relationship', function (Dep) {

    return Dep.extend({

        getActionList: function () {
           let  list = [];
           if(this.model.get('collection').length > 1) {
               list = list.concat( [
                   {
                       action: 'compare',
                       label: 'Compare',
                       data: {
                           key: this.options.key
                       }
                   },
                   {
                       action: 'merge',
                       label: 'Merge',
                       data: {
                           key: this.options.key
                       }
                   }
               ])
           }
            return list;
        }
    })
});
