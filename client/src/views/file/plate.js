/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/plate', 'views/file/list',
    Dep => Dep.extend({

        name: 'plate',

        setup() {
            this.viewMode = 'plate';

            Dep.prototype.setup.call(this);

            this.collection.maxSize = 20;

            this.getStorage().set('list-view', 'File', 'plate');
        },

        getRecordViewName: function () {
            const viewName = Dep.prototype.getRecordViewName.call(this);

            return viewName || this.getMetadata().get('clientDefs.' + this.scope + '.recordViews.plate') || 'views/file/record/plate';
        }


    })
);

