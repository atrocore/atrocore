/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/layout-profile/modals/dashboard-layout', 'views/layout-profile/modals/navigation',
    (Dep) => Dep.extend({
        className: 'full-page-modal',
        afterRender() {
            if (window.layoutSvelteDashboardLayout) {
                try {
                    window.layoutSvelteDashboardLayout.$destroy()
                } catch (e) {
                }
            }

            this.$el.find('.modal-body').css('paddingTop', 0);

            if(!this.$el.find('.navigation').length) {
                return;
            }

            var dashletList = Object.keys(this.getMetadata().get('dashlets') || {}).sort(function (v1, v2) {
                return this.translate(v1, 'dashlets').localeCompare(this.translate(v2, 'dashlets'));
            }.bind(this));

            this.dashletList = [];

            dashletList.forEach(function (item) {
                var aclScope = this.getMetadata().get('dashlets.' + item + '.aclScope') || null;
                if (aclScope) {
                    if (!this.getAcl().check(aclScope)) {
                        return;
                    }
                }
                var accessDataList = this.getMetadata().get(['dashlets', item, 'accessDataList']) || null;
                if (accessDataList) {
                    if (!Espo.Utils.checkAccessDataList(accessDataList, this.getAcl(), this.getUser())) {
                        return false;
                    }
                }
                this.dashletList.push(item);
            }, this);

            window.layoutSvelteDashboardLayout = new Svelte.DashboardLayout({
                target: this.$el.find('.navigation').get(0),
                props: {
                    params: {
                        list: dashletList,
                        layout: this.model.get(this.field),
                        onSaved: (layout) => {
                            let attributes = {};
                            attributes[this.field] = layout;
                            this.close();
                            this.notify('Loading...');
                            this.model.save(attributes, {
                                patch: true
                            }).then(() => {
                                this.notify('Done', 'success')
                            });
                        }
                    }
                }
            })
        }
    })
);
