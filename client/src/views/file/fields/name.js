/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('views/file/fields/name', 'views/fields/varchar',
    Dep => Dep.extend({

        detailTemplate: "fields/name/detail",

        editTemplate: 'file/fields/name/edit',

        validations: ['name'],

        data() {
            let data = _.extend({attachmentId: this.model.get("id")}, Dep.prototype.data.call(this));

            const parts = (data.value || '').split('.');
            if (parts.length >= 2) {
                parts.pop();
            }

            data['fileExt'] = this.model.get('extension');
            data['valueWithoutExt'] = parts.length > 1 ? parts.join('.') : parts[0];

            return data;
        },

        fetch() {
            let data = {};

            let $el = this.$element;
            if ($el) {
                let value = $el.val();

                if (this.model.get('extension')) {
                    value += '.' + this.model.get('extension');
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

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list' || this.mode === 'listLink') {
                let id = this.model.get('id');
                let name = this.model.get('name');

                this.$el.html('<div class="file-link"><a href="' + this.getBasePath() + '?entryPoint=download&id=' + id + '" download="" title="' + this.translate('Download') + '"> <span class="glyphicon glyphicon-download-alt small"></span></a> <a href="/#File/view/' + id + '" title="' + name + '" class="link" data-id="' + id + '">' + Handlebars.Utils.escapeExpression(name) + '</a></div>');
            }
        },

    })
);
