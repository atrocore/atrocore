/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/selection/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        selectionViewMode: 'standard',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupCustomButtons();
            this.listenTo(this.model, 'after:change-mode', (mode) => {
                if (mode === 'detail') {
                    this.setupCustomButtons();
                }
            })
        },

        setupCustomButtons() {
            this.addMenuItem('buttons', {
                'name': 'merge',
                'action': 'showSelectionView',
                style: this.selectionViewMode === 'merge' ? 'primary' : null,
                'html': '<i class="ph ph-arrows-merge "></i> ' + this.translate('Merge')
            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'compare',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'compare' ? 'primary' : null,
                html: '<i class="ph ph-arrows-left-right"></i> ' + this.translate('Compare')
            }, true, false, true);

            this.addMenuItem('buttons', {
                name: 'standard',
                action: 'showSelectionView',
                style: this.selectionViewMode === 'standard' ? 'primary' : null,
                html: '<i class="ph ph-list"></i> ' + this.translate('Standard')
            }, true, false, true);
        },

        actionShowSelectionView: function (data) {
            if (this.selectionViewMode === data.name) {
                return;
            }

            this.selectionViewMode = data.name;

            this.reloadStyle(this.selectionViewMode);

            this.model.trigger('selection-view-mode:change', this.selectionViewMode);

            if(this.selectionViewMode === 'standard') {
                let recordView = this.getMainRecord();
                recordView.trigger('detailPanelsLoaded', { list: recordView.getMiddlePanels().concat(recordView.getView('bottom')?.panelList || []) });
            }
        },

        reloadStyle(selected) {

            ['compare', 'standard', 'merge'].forEach(name => {
                $(`.action[data-name="${name}"]`).removeClass('primary');
            })

            $(`.action[data-name="${selected}"]`).addClass('primary');
        }
    });
});

