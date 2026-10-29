/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/subtype/detail', ['views/detail', 'views/subtype/breadcrumbs'], (Dep, Breadcrumbs) => Dep.extend({

    getBreadcrumbsItems(isAdmin = false) {
        return Breadcrumbs.prepare(this, Dep.prototype.getBreadcrumbsItems.call(this, isAdmin));
    },

    /**
     * The followers belong to the supertype record, following the subtype record follows the supertype record.
     */
    getHeaderOptions() {
        const options = Dep.prototype.getHeaderOptions.call(this);

        const supertypeModel = this.model.supertypeModel;
        if (!supertypeModel || !this.canFollowSupertype(supertypeModel.name)) {
            options.recordButtons.followers = null;
            return options;
        }

        options.recordButtons.followers = supertypeModel.get('followersNames') || {};
        options.callbacks.onFollow = () => this.setSupertypeFollowers(supertypeModel, true);
        options.callbacks.onUnfollow = () => this.setSupertypeFollowers(supertypeModel, false);

        return options;
    },

    canFollowSupertype(supertype) {
        return !this.getMetadata().get(['scopes', supertype, 'streamDisabled'])
            && !this.getMetadata().get(['scopes', supertype, 'followDisabled'])
            && this.getAcl().check(supertype, 'stream');
    },

    setSupertypeFollowers(supertypeModel, isFollowed) {
        const userId = this.getUser().get('id');
        const followersNames = Object.assign({}, supertypeModel.get('followersNames') || {});

        if (isFollowed) {
            followersNames[userId] = this.getUser().get('name');
        } else {
            delete followersNames[userId];
        }

        supertypeModel.set({
            isFollowed: isFollowed,
            followersIds: Object.keys(followersNames),
            followersNames: followersNames
        });
    },

}));
