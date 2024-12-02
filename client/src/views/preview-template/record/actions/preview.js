/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/preview-template/record/actions/preview', 'view',
    Dep => Dep.extend({

        _template: '',

        showHtmlPreview() {
            const htmlTemplateId = this.options.button.name;
            this.createView('htmlPreviewModal' + htmlTemplateId, 'views/preview-template/record/modals/preview', {
                htmlTemplateId: htmlTemplateId,
                entityId: this.model.get('id'),
                modalTitle: this.options.button.label || null
            }, view => {
                view.render();
            });
        }
    })
);
