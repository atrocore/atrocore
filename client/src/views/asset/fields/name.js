/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/asset/fields/name', 'views/fields/varchar',
    Dep => Dep.extend({

        detailTemplate: "fields/name/detail",

        editTemplate: 'asset/fields/name/edit',

        validations: ['name'],

        extension: null,

        data() {
            let data = _.extend({attachmentId: this.model.get("fileId")}, Dep.prototype.data.call(this));

            const parts = (data.value || '').split('.');

            this.extension = parts.length >= 2 ? parts.pop() : '';

            data['fileExt'] = this.extension;
            data['valueWithoutExt'] = parts.length > 1 ? parts.join('.') : parts[0];

            return data;
        },

        fetch() {
            let data = {};

            let $el = this.$element;
            if ($el) {
                let value = $el.val();

                if (this.extension) {
                    value += '.' + this.extension;
                }

                if (this.params.trim || this.forceTrim) {
                    if (typeof value.trim === 'function') {
                        value = value.trim();
                    }
                }
                data[this.name] = value ? value : null;
            }

            return data;
        },

        validateName() {
            let name = this.model.get(this.name);
            let fileNameRegexPatternString = this.getConfig().get('fileNameRegexPattern');
            let fileNameRegexPattern = this.convertStrToRegex(fileNameRegexPatternString);

            if (fileNameRegexPattern && !fileNameRegexPattern.test(name)) {
                let msg = this.translate('fileNameNotValidByUserRegex', 'exceptions', 'Asset').replace('%s', fileNameRegexPattern);
                this.showValidationMessage(msg, '[name="' + this.name + '"]');
                return true;
            }

            return false;
        },
    })
);
