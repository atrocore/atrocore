/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/user-profile/detail', 'views/detail', Dep => {

    return Dep.extend({

        scope: 'UserProfile',

        recordView: 'views/user-profile/record/detail',

        navigateButtonsDisabled: true,

        getRecordViewName() {
            return this.recordView;
        },

        getBreadcrumbsItems() {
            return [
                {
                    label: this.getLanguage().translate(this.scope, 'scopeNamesPlural'),
                },
                {
                    label: this.model.get('name'),
                }
            ];
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.$el.find('.header-items').hide();
        },

    });
});

