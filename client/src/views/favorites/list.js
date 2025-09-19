/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/favorites/list', 'view', function (Dep) {

    return Dep.extend({

        template: 'favorites/list',

        class: 'favorites-items',

        showEmptyPlaceholder: false,

        hasArrow: false,

        plusIcon: 'client/img/icons/phosphor-regular/plus-square.svg',

        setup() {
            Dep.prototype.setup.call(this);
            this.class = this.options.class || this.class;

            if (typeof this.options.showEmptyPlaceholder === 'boolean') {
                this.showEmptyPlaceholder = this.options.showEmptyPlaceholder;
            }

            if (typeof this.options.hasArrow === 'boolean') {
                this.hasArrow = this.options.hasArrow;
            }

            this.listenTo(this.model, 'favorites:update', () => {
                this.reRender();
            });
        },

        data: function () {
            return {
                class: this.class,
                hasIcons: !this.getConfig().get('favoritesIconsDisabled'),
                favoritesList: this.getFavoritesList().map(tab => this.getParentView().getTabDefs(tab) ?? tab),
                showEmptyPlaceholder: this.showEmptyPlaceholder,
                activeTab: this.getRouter().getLast().controller,
                hasArrow: this.hasArrow,
                plusIcon: this.plusIcon
            }
        },

        getFavoritesList: function () {
            let list = this.model.get('favoritesList') || [];
            if (typeof list === 'object') {
                list = Object.values(list);
            }

            return list.filter(tab => this.getAcl().checkScope(tab, 'read') && !!this.getMetadata().get(['scopes', tab, 'tab']));
        },

    });

});


