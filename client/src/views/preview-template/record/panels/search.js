/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/record/panels/search', 'views/record/panels/search',
    Dep => Dep.extend({

        setup() {
            this.scope = this.model.get('entityType');

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:entityType', () => {
                this.scope = this.model.get('entityType');

                let data = {};
                if (this.model.get('data')) {
                    data = this.model.get('data');
                }
                if (typeof data.whereScope === 'undefined' || data.whereScope !== this.scope) {
                    data = _.extend(data, {
                        where: null,
                        whereData: null,
                        whereScope: this.scope,
                    });
                    this.model.set({data: data});
                }

                this.setupSearchPanel();
            });
        }
    })
);
