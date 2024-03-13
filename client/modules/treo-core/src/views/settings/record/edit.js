/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/settings/record/edit', 'class-replace!treo-core:views/settings/record/edit', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:isStreamPanelFirst', function () {
                if (this.model.get('isStreamPanelFirst')) {
                    this.model.set({isStreamSide: false});
                }
            }.bind(this));

            this.listenTo(this.model, 'change:isStreamSide', function () {
                if (this.model.get('isStreamSide')) {
                    this.model.set({isStreamPanelFirst: false});
                }
            }.bind(this));
        }
    });
});

