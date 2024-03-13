/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/stream/panel', 'class-replace!treo-core:views/stream/panel',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            delete this.events['focus textarea.note'];

            this.events['click textarea.note'] = e => {
                this.enablePostingMode();
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.listenToOnce(this.collection, 'sync', () => {
                setTimeout(() => {
                    this.stopListening(this.model, 'all');
                    this.stopListening(this.model, 'destroy');
                    this.listenTo(this.model, 'all', event => {
                        if (!['sync', 'after:relate', 'after:attributesSave'].includes(event)) {
                            return;
                        }
                        let initialTotal = this.collection.total;
                        this.collection.fetchNew({
                            success: function () {
                                this.collection.total += initialTotal;
                            }.bind(this)
                        });
                    });

                    this.listenTo(this.model, 'destroy', () => {
                        this.stopListening(this.model, 'all');
                    });
                }, 500);
            });
        },
    })
);