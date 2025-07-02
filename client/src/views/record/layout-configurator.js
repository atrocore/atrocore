/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/record/layout-configurator', 'view', function (Dep) {

    return Dep.extend({

        template: 'record/layout-configurator',

        alignRight: false,

        cleanup: null,

        events: {
            'click .dropdown-toggle': function (e) {
                e.currentTarget.parentElement.classList.toggle('open');
                if (e.currentTarget.parentElement.classList.contains('open')) {
                    this.updatePosition();
                } else if (this.cleanup) {
                    this.cleanup();
                }
            },
            'click .layout-editor': function (e) {
                // open modal view
                this.showLayoutEditorModal()
            },
            'click .layout-profile-item': function (e) {
                const layoutProfileId = $(e.target).data('id')
                this.notify('Loading...')
                this._helper.layoutManager.savePreference(this.scope, this.viewType, this.relatedScope, layoutProfileId, () => {
                    this.notify(false)
                    this.refreshLayout()
                })
            }
        },

        setup() {
            Dep.prototype.setup.call(this)
            this.layoutData = this.options.layoutData
            this.scope = this.options.scope
            this.viewType = this.options.viewType
            this.relatedScope = this.options.relatedScope

            if (this.options.alignRight && typeof this.options.alignRight === 'boolean') {
                this.alignRight = this.options.alignRight;
            }
        },

        data: function () {
            const activeProfileId = (this.layoutData?.selectedProfileId || this.layoutData?.storedProfile?.id)
            const layoutProfiles = (this.layoutData?.storedProfiles || [])
                .map(sp => ({...sp, isSelected: sp.id === activeProfileId}))
            const canConfigure = this.getAcl().check('LayoutProfile', 'edit')

            return {
                showLayoutEditor: canConfigure || layoutProfiles.length > 0,
                canConfigure: canConfigure,
                storedProfiles: layoutProfiles,
                selectedProfileId: this.layoutData?.selectedProfileId,
                linkClass: this.options.linkClass ?? '',
                alignRight: this.alignRight,
            };
        },

        // afterRender() {
        //     Dep.prototype.afterRender.call(this);
        // },

        updatePosition() {
            const button = this.$el.find('.dropdown-toggle')[0];
            const dropdown = this.$el.find('.dropdown-menu')[0];

            if (!button || !dropdown) {
                return;
            }

            this.cleanup = window.floatingUi.autoUpdate(button, dropdown, () => {
                window.floatingUi.computePosition(button, dropdown, {
                    strategy: 'fixed',
                    placement: 'bottom-start',
                    middleware: [window.floatingUi.offset(5), window.floatingUi.flip({
                        crossAxis: 'alignment',
                        fallbackAxisSideDirection: 'start'
                    }), window.floatingUi.shift({mainAxis: true})],
                }, {animationFrame: true}).then(({x, y}) => {
                    Object.assign(dropdown.style, {
                        left: `${x}px`,
                        top: `${y}px`,
                    });
                })
            });
        },

        showLayoutEditorModal() {
            let layoutProfileId = this.layoutData?.selectedProfileId || this.layoutData?.storedProfile?.id;
            let layoutProfileName = ''
            if (!layoutProfileId) {
                layoutProfileId = this.getUser().get('layoutProfileId')
                layoutProfileName = this.getUser().get('layoutProfileName')
            } else {
                this.layoutData.storedProfiles.forEach((profile) => {
                    if (profile.id === layoutProfileId) {
                        layoutProfileName = profile.name
                    }
                })
            }
            this.createView('dialog', 'views/admin/layouts/modals/edit', {
                scope: this.scope,
                type: this.viewType,
                relatedScope: this.relatedScope,
                layoutProfileId: layoutProfileId,
                layoutProfileName: layoutProfileName,
                el: '[data-view="dialog"]',
            }, view => {
                view.render()
                this.listenToOnce(view, 'close', (data) => {
                    this.clearView('dialog');
                    if (data && data.layoutIsUpdated) {
                        this.refreshLayout()
                    }
                });
            });
        },

        refreshLayout() {
            this.trigger('refresh')
        }
    });
})
;
