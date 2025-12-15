/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/selection/record/detail/detail-comparison-view', 'views/record/right-side-view-panel', function (Dep) {

    return Dep.extend({

        template: 'record/detail',

        layoutName: 'selection',

        setup() {
            Dep.prototype.setup.call(this);

            if (!this.model.isNew() && (this.type === 'detail' || this.type === 'edit') && !this.isSmall) {
                this.listenTo(this, 'after:render', () => {
                    this.applyOverviewFilters();
                });
                this.listenTo(this.model, 'sync overview-filters-changed', () => {
                    this.applyOverviewFilters();
                });
            }
        },

        applyOverviewFilters() {
            let thisClone = Espo.utils.clone(this);
            thisClone.scope = 'Selection';

            Dep.prototype.applyOverviewFilters.call(thisClone);

        },
    });
});
