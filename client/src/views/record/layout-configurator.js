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

        dropdown: null,

        cleanup: null,

        events: {
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

            this.once('remove', () => {
                if (this.dropdown) {
                    this.dropdown.destroy();
                    this.dropdown = null;
                }
            });
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

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.dropdown) {
                this.dropdown.destroy();
            }

            const button = this.$el.find('.dropdown-toggle')[0];
            const dropdown = this.$el.find('.dropdown-menu')[0];

            if (!button || !dropdown) {
                return;
            }

            this.dropdown = new window.Dropdown(button, dropdown, {
                placement: 'bottom-start',
                onDropdownShow: dropdown => {
                    dropdown.parentElement?.classList.add('open');
                },
                onDropdownHide: dropdown => {
                    dropdown.parentElement?.classList.remove('open');
                },
                strategy: 'fixed',
                offset: {
                    mainAxis: 5
                },
                flip: {
                    crossAxis: 'alignment',
                    fallbackAxisSideDirection: 'start'
                },
                shift: {
                    mainAxis: true
                }
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
