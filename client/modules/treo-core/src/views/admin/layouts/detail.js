/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/admin/layouts/detail', 'class-replace!treo-core:views/admin/layouts/detail', function (Dep) {

    return Dep.extend({
        loadLayout: function (callback) {
            const promiseList = [];
            let model, layout;

            promiseList.push(
                new Promise(function (resolve) {
                    this.getModelFactory().create(this.scope, function (m) {
                        this.getHelper().layoutManager.get(this.scope, this.type, function (layoutLoaded) {
                            layout = layoutLoaded;
                            model = m;
                            resolve();
                        }, false);
                    }.bind(this));
                }.bind(this))
            );

            if (~['detail', 'detailSmall'].indexOf(this.type)) {
                promiseList.push(
                    new Promise(function (resolve) {
                        this.getHelper().layoutManager.get(this.scope, 'sidePanels' + Espo.Utils.upperCaseFirst(this.type), function (layoutLoaded) {
                            this.sidePanelsLayout = layoutLoaded;
                            resolve();
                        }.bind(this), false);
                    }.bind(this))
                );
            }

            Promise.all(promiseList).then(function () {
                this.readDataFromLayout(model, layout);
                if (callback) {
                    callback();
                }
            }.bind(this));
        },
    });
});
