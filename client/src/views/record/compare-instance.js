/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/compare-instance','views/record/compare', function (Dep) {

    return Dep.extend({
        instanceComparison: true,

        getDistantComparisonModels(model) {
            let models  = [];
            this.distantModelsAttribute.forEach((modelAttribute, index) => {

                if('_error' in modelAttribute){
                    this.instances[index]['_error'] = modelAttribute['_error'];
                }
                let  m = model.clone();
                for(let key in modelAttribute){
                    let el = modelAttribute[key];
                    let instanceUrl = this.instances[index].atrocoreUrl;
                    if(key.includes('PathsData')){
                        if( el && ('thumbnails' in el)){
                            for (let size in el['thumbnails']){
                                modelAttribute[key]['thumbnails'][size] = instanceUrl + '/' + el['thumbnails'][size]
                            }
                        }
                    }
                }
                m.set(modelAttribute);
                models.push(m);
            })

            return models;
        },

        buildComparisonTableColumn() {
            let columns = [];
                columns.push({name: this.translate('instance', 'labels', 'Synchronization')});
                columns.push({name: this.translate('current', 'labels', 'Synchronization')});
                this.instances.forEach(instance => {
                    columns.push({
                        name: instance.name,
                        _error: instance._error
                    })
                });
            return columns;
        }
    });
});