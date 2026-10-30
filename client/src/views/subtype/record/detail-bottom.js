/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

/**
 * A subtype record has no stream of its own: the stream of the supertype record is shown instead.
 */
Espo.define('views/subtype/record/detail-bottom', 'views/record/detail-bottom', Dep => Dep.extend({

    setupOptionalPanels() {
        Dep.prototype.setupOptionalPanels.call(this);

        const supertypeModel = this.model.supertypeModel;
        if (
            !this.streamPanel
            || !supertypeModel
            || this.getMetadata().get(['scopes', supertypeModel.name, 'streamDisabled'])
            || !this.getAcl().check(supertypeModel.name, 'stream')
        ) {
            return;
        }

        this.setupStreamPanel();

        const panel = this.panelList[this.panelList.length - 1];
        panel.model = supertypeModel;
        panel.scope = supertypeModel.name;
    },

}));
