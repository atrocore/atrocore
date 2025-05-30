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
            const previewTemplateId = this.options.previewTemplateId;
            this.createView('htmlPreviewModal' + previewTemplateId, 'views/preview-template/record/modals/preview', {
                previewTemplateId: previewTemplateId,
                entityId: this.model.get('id'),
                modalTitle: this.options.label || null
            }, view => {
                view.render();
            });
        }
    })
);
