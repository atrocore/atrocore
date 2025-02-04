/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-followed-record/fields/entity-id', 'views/fields/link',
    Dep => Dep.extend({

        setup() {
            this.idName = 'entityId';
            this.nameName = 'entityName';

            this.foreignScope = this.getForeignScope();
            this.listenTo(this.model, 'change:entityType', () => {
                this.foreignScope = this.getForeignScope();
                this.reRender();
            });

            Dep.prototype.setup.call(this);
        },

        getForeignScope() {
            return this.model.get('entityType');
        },

    })
);
