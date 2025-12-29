/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/fields/hierarchy-routes', 'views/fields/link', Dep => {

    return Dep.extend({

        type: 'jsonArray',

        listTemplate: 'fields/hierarchy-routes/detail',

        detailTemplate: 'fields/hierarchy-routes/detail',

        setup() {
            this.idName = this.name;
            this.nameName = this.name + 'Names';

            Dep.prototype.setup.call(this);

            this.foreignScope = this.model.name;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.valueIsNull = this.model.get(this.name) === null;
            data.entityName = this.model.name;
            data.routes = this.model.get(this.name + 'Names') || [];

            return data;
        },

        chooseMultipleOnSearch: function () {
            return false;
        },
    });
});

