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
 * A subtype record has no stream and history of its own: they belong to the supertype record.
 */
Espo.define('views/subtype/record/detail', 'views/record/detail', Dep => Dep.extend({

    bottomView: 'views/subtype/record/detail-bottom',

    canLoadActivities() {
        const supertypeModel = this.model.supertypeModel;
        if (!supertypeModel) {
            return false;
        }

        return !this.getMetadata().get(['scopes', supertypeModel.name, 'streamDisabled'])
            && this.getAcl().check(supertypeModel.name, 'stream');
    },

    getSvelteSideViewProps(parentView) {
        const props = Dep.prototype.getSvelteSideViewProps.call(this, parentView);

        const supertypeModel = this.model.supertypeModel;
        if (!supertypeModel) {
            return props;
        }

        props.loadActivities = callback => {
            parentView.createView('activities', 'views/record/activities', {
                el: parentView.options.el + ' .right-side-view .activities',
                model: supertypeModel,
                mode: this.mode,
                scope: supertypeModel.name,
                recordHelper: this.recordHelper,
                recordViewObject: this.recordViewObject
            }, view => {
                if (callback) {
                    callback(view);
                }
                view.render();
            });
        };

        return props;
    },

}));
