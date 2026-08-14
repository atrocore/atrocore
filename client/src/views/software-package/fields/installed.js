/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/software-package/fields/installed', 'views/fields/bool', Dep => Dep.extend({

    afterRender() {
        Dep.prototype.afterRender.call(this);

        if (!['list', 'listLink'].includes(this.mode)) {
            return;
        }

        let html;
        if (this.model.get('id') === 'Atro') {
            html = `<i class="ph-fill ph-check-circle text-success"></i>`;
        } else if (this.model.get('installed')) {
            html = `<i class="ph ph-check-circle text-success" title="${this.translate('installed', 'fields', 'SoftwarePackage')}"></i>`;
        } else {
            html = `<i class="ph ph-circle text-muted" title="${this.translate('notInstalled', 'labels', 'SoftwarePackage')}"></i>`;
        }

        this.$el.html(html);
    },

}));
