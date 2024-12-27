/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/type', 'views/fields/link',
    Dep => Dep.extend({
        selectBoolFilterList: ['onlyAllowFileTypes'],
        boolFilterData: {
            onlyAllowFileTypes () {
                if(this.model.attributes['_uploadForEntityData']) {
                    let data = this.model.attributes['_uploadForEntityData'] ?? {}
                    if(data['scope'] && data['link']){
                        return this.getMetadata().get(['entityDefs', data['scope'], 'fields', data['link'], 'fileTypes']);
                    }
                }
            }
        }
    })
);
