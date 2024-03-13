/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/record/detail-small', 'class-replace!treo-core:views/record/detail-small',
    Dep => Dep.extend({

        template: 'treo-core:record/detail-small',

        isWide: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.isWide = this.isWide || this.sideDisabled;
        },

        prepareLayoutAfterConverting(layout) {
            layout = Dep.prototype.prepareLayoutAfterConverting.call(this, layout);

            (layout || []).forEach(panel => {
                (panel.rows || []).forEach(row => {
                    if (row[0]) {
                        if (!row[1]) {
                            row[0].fullWidth = true;
                        }
                    }
                });
            });

            return layout;
        },

        createSideView() {
            this.wait(true);
            let el = this.options.el || '#' + (this.id);
            this.createView('side', this.sideView, {
                model: this.model,
                scope: this.scope,
                el: el + ' .side',
                type: this.type,
                readOnly: this.readOnly,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this
            }, view => {
                if (!view.panelList.length) {
                    this.isWide = true;
                }
                this.wait(false);
            });
        },

    })
);