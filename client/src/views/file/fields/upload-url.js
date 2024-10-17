/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/upload-url', 'views/fields/varchar',
    Dep => Dep.extend({

        setup() {
            this._template = `<div class="input-group"><input id="upload-url-input" type="text" placeholder="${this.translate('putUploadUrl', 'labels', 'File')}" class="main-element form-control upload-url" name="upload-url" value="" autoComplete="off"><span id="upload-via-url" class="input-group-btn"><span class="form-control btn btn-primary">${this.translate('Upload', 'labels', 'File')}</span></span></div>`
            Dep.prototype.setup.call(this)
        }
    })
);
