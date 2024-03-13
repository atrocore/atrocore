/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:controllers/composer', 'controller', function (Dep) {

    return Dep.extend({

        defaultAction: "list",

        list: function () {
            this.collectionFactory.create('Composer', function (collection) {
                collection.maxSize = this.getConfig().get('recordsPerPage') || collection.maxSize;
                collection.sortBy = 'name';
                collection.asc = false;

                this.main('treo-core:views/composer/list', {
                    scope: 'Composer',
                    collection: collection
                });
            }, this);
        },
    });
});
