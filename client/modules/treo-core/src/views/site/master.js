/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/site/master', 'class-replace!treo-core:views/site/master',
    Dep => Dep.extend({

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
            error: '--error'
        },

        afterRender() {
            if ($(":root").length > 0) {
                const config = this.getConfig().get('customStylesheetsList') || [],
                      theme = this.getPreferences().get('theme') || this.getConfig().get('theme');

                if (config[theme]) {
                    (Object.keys(this.styleVariableMap) || []).forEach(param => {
                        if (config[theme][param]) {
                            $(":root")[0].style.setProperty(this.styleVariableMap[param], config[theme][param]);
                        }
                    });
                }
            }
        }

    })
);


