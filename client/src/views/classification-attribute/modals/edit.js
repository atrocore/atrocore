/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/classification-attribute/modals/edit', 'views/modals/edit',
    Dep => Dep.extend({

        fullFormDisabled: true,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this, 'after:save', model => {
                $('.action[data-action=refresh][data-panel=classificationAttributes]').click();
                /**
                 * Show another notify message if attribute '%s' was linked not for all chosen channels
                 */
                if (model.get('channelsNames') === true) {
                    let message = this.getLanguage().translate('savedForNotAllChannels', 'messages', 'ClassificationAttribute');
                    Espo.Ui.notify(message.replace('%s', model.get('attributeName')), 'success', 1000 * 60 * 60 * 2, true);
                }
            });
        }

    })
);

