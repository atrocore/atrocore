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

    setup() {
        Dep.prototype.setup.call(this);

        this.setupSupertypeNavigation();
    },

    /**
     * The record is shown via its subtype record, the navigation goes through the records of the supertype list the
     * record is opened from.
     */
    setupSupertypeNavigation() {
        const collection = this.options.supertypeCollection;
        if (this.navigateButtonsDisabled || !collection) {
            this.removeMenuItem('navigation', true);
            return;
        }

        this.supertypeCollection = collection;

        this.indexOfRecord = this.options.indexOfRecord;
        if (this.indexOfRecord === undefined || this.indexOfRecord === null) {
            this.indexOfRecord = collection.indexOf(collection.get(this.model.get('supertypeId')));
        }

        this.hasPrevious = this.indexOfRecord > 0;
        this.hasNext = this.indexOfRecord < collection.total - 1
            || collection.total === -1
            || (collection.total === -2 && this.indexOfRecord < collection.length - 1);
    },

    actionPrevious() {
        if (!this.supertypeCollection || !(this.indexOfRecord > 0)) {
            return;
        }

        this.switchToModelByIndex(this.indexOfRecord - 1);
    },

    actionNext() {
        const collection = this.supertypeCollection;
        if (!collection || !this.hasNext) {
            return;
        }

        const indexOfRecord = this.indexOfRecord + 1;
        if (indexOfRecord <= collection.length - 1) {
            this.switchToModelByIndex(indexOfRecord);
            return;
        }

        this.listenToOnce(collection, 'sync', () => {
            this.switchToModelByIndex(indexOfRecord);
        });
        collection.fetch({more: true, remove: false});
    },

    switchToModelByIndex(indexOfRecord) {
        const model = this.supertypeCollection && this.supertypeCollection.at(indexOfRecord);
        if (!model) {
            return;
        }

        const mode = this.mode === 'edit' ? 'edit' : 'view';

        this.getRouter().navigate(`#${model.name}/${mode}/${model.id}`, {trigger: false});
        this.getRouter().dispatch(model.name, mode, {
            id: model.id,
            model: model,
            indexOfRecord: indexOfRecord
        });
    },

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
