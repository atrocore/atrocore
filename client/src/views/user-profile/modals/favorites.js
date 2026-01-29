/*
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/modals/favorites', 'views/layout-profile/modals/favorites',
    (Dep) => Dep.extend({
        actionResetToDefault() {
            this.notify('Saving...');

            this.getPreferences().save({
                _skipIsEntityUpdated: true,
                favoritesList: null
            }, { patch: true }).then(() => {
                this.notify('Saved', 'success');
                this.close();
                this.getPreferences().trigger('favorites:update');
                window.dispatchEvent(new CustomEvent('favorites:update', { detail: this.getPreferences().get('favoritesList') }));
            });
        }
    })
);