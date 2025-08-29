/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */


Espo.define('views/record/right-side-view', ['views/record/detail', 'view-record-helper'], function (Dep, ViewRecordHelper) {

    return Dep.extend({
        template: 'record/right-side-view',

        middleView: 'views/record/right-side-view-middle',

        bottomView: 'views/record/right-side-view-bottom',

        layoutName: 'rightSideView',

        setup: function () {
            if (typeof this.model === 'undefined') {
                throw new Error('Model has not been injected into record view.');
            }

            this.recordHelper = new ViewRecordHelper(this.defaultFieldStates, this.defaultFieldStates);

            this.once('remove', function () {
                if (this.isChanged) {
                    this.resetModelChanges();
                }
                this.setIsNotChanged();
                $(window).off('scroll.detail-' + this.numId);
            }, this);

            this.numId = Math.floor((Math.random() * 10000) + 1);
            this.id = Espo.Utils.toDom(this.entityType) + '-' + Espo.Utils.toDom(this.type) + '-' + this.numId;

            this.events = {};

            if (!this.editModeDisabled) {
                if ('editModeDisabled' in this.options) {
                    this.editModeDisabled = this.options.editModeDisabled;
                } else if (this.getMetadata().get(['scopes', this.model.name, 'disabled'])) {
                    this.editModeDisabled = true
                }
            }

            this.buttonsDisabled = this.options.buttonsDisabled || this.buttonsDisabled;

            // for backward compatibility
            // TODO remove in 5.6.0
            if ('buttonsPosition' in this.options && !this.options.buttonsPosition) {
                this.buttonsDisabled = true;
            }

            if ('isWide' in this.options) {
                this.isWide = this.options.isWide;
            }

            if ('bottomView' in this.options) {
                this.bottomView = this.options.bottomView;
            }

            this.sideDisabled = this.options.sideDisabled || this.sideDisabled;
            this.bottomDisabled = this.options.bottomDisabled || this.bottomDisabled;

            this.readOnlyLocked = this.readOnly;
            this.readOnly = this.options.readOnly || this.readOnly;

            this.inlineEditDisabled = this.inlineEditDisabled || this.getMetadata().get(['clientDefs', this.scope, 'inlineEditDisabled'])
                || this.getMetadata().get(['scopes', this.model.name, 'disabled']) || false;

            this.inlineEditDisabled = this.options.inlineEditDisabled || this.inlineEditDisabled;

            this.listenTo(this.model, 'after:change-mode', (mode) => {
                if (mode === this.mode) {
                    return;
                }
                if (mode === 'edit') {
                    this.setEditMode();
                } else {
                    this.setDetailMode()
                }
            })
        },

        triggerModeChangedOnModel(mode) {

        },

        setupBeforeFinal: function () {
            this.manageAccess();

            this.dependencyDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.formDependency') || {}, this.dependencyDefs);
            this.initDependancy();

            this.setupFieldLevelSecurity();
        },

        afterRender: function () {
            this.initListenToInlineMode();
        }
    });
});
