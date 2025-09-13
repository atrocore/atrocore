/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/settings/fields/style', 'views/user-profile/fields/style', Dep => {

    return Dep.extend({
        updateStylesAfterSave(){
           Dep.prototype.updateStylesAfterSave.call(this);
            let customStylesheetPath = this.model.get('customStylesheetPath');
            if (this.model.get('customStylesheet') && customStylesheetPath) {
                customStylesheetPath = customStylesheetPath.replace('public/', '');
                let customLink = $('#custom-stylesheet-global');
                if (customLink.length > 0) {
                    customLink.attr('href', customStylesheetPath + `?r=${Date.now()}`);
                } else {
                    $('head').append('<link href="' + customStylesheetPath + '" rel="stylesheet" id="custom-stylesheet-global">');
                }
            } else {
                $('#custom-stylesheet-global').remove();
            }
        },

        updateStylesAfterChange(){
            if(this.getPreferences().get('styleId')) {
                return;
            }

            if(!this.model.get(this.name + 'Id')) {
                this.reloadStyle()
                return;
            }

            const style = this.getStyle(this.model.get(this.name + 'Id'));
            this.reloadStyle(style);
        },

        shouldHide() {
            return false;
        },

        updatePreferences(key, value) {
            if(key === 'styleId') {
                return;
            }
            this.getPreferences().set('styleId', this.model.get(this.name + 'Id'));
        },

    });
});
