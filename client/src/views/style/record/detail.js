/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/style/record/detail', ['views/record/detail', 'treo-core:views/site/master'], (Dep, Master) => {

    return Dep.extend({

        styleVariableMap: {
            navigationManuBackgroundColor: '--nav-menu-background',
            navigationMenuFontColor: '--nav-font-color',
            linkFontColor: '--link-color',
            primaryColor: '--primary-color',
            secondaryColor: '--secondary-color',
            primaryFontColor: '--primary-font-color',
            secondaryFontColor: '--secondary-font-color',
            labelColor: '--label-color',
            anchorNavigationBackground: '--anchor-nav-background',
            iconColor: '--icon-color',
            primaryBorderColor: '--primary-border-color',
            secondaryBorderColor: '--secondary-border-color',
            panelTitleColor: '--panel-title-color',
            headerTitleColor: '--header-title-color',
            success: '--success',
            notice: '--notice',
            information: '--information',
            error: '--error',
            actionIconColor: '--action-icon-color',
            statusIconColor: '--status-icon-color',
            highlightingColorForRequired: '--highlighting-color-for-required',
            highlightingColorForDataQuality: '--highlighting-color-for-data-quality'
        },

        rgbVariables: ['navigationManuBackgroundColor', 'navigationMenuFontColor'],

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'after:save after:inlineEditSave', () => {
                this.getStorage().clear('icons', 'navigationIconColor');
                let style = this.getThemeManager().getStyle();
                if(style.id === this.model.id) {
                    let master = new Master();
                    master.initStyleVariables(this.model.attributes);
                }
                setTimeout(() => {
                    this.showReloadPageMessage()
                }, 2000);
            });
        }
    });
});

