/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/matched-record/fields/master', 'views/fields/link', Dep => {

    return Dep.extend({

        setup() {
            this.options.foreignScope = this.model.get('masterEntity');

            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:matchingId', () => {
                this.model.set('masterId', null);
                this.model.set('masterName', null);

                $.each((this.getConfig().get('referenceData').Matching || {}), (code, matching) => {
                    if (matching.id === this.model.get('matchingId')) {
                        this.foreignScope = matching.masterEntity;
                        this.reRender();
                    }
                })
            });
        },

    });
});