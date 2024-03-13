/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

Espo.define('treo-core:views/fields/wysiwyg', 'class-replace!treo-core:views/fields/wysiwyg',
    Dep => Dep.extend({

        listTemplate: 'treo-core:fields/wysiwyg/list',

        detailTemplate: 'treo-core:fields/wysiwyg/detail',

        detailMaxHeight: 400,

        showMoreText: false,

        seeMoreDisabled: false,

        events: {
            'click a[data-action="seeMoreText"]': function (e) {
                this.showMoreText = true;
                this.reRender();
            },
            'keyup div.note-editable': function (e) {
                this.updateTextCounter(this.$el.find('.note-editable').html());
            },
            'keyup textarea.note-codable': function (e) {
                this.updateTextCounter(this.$el.find('.note-codable').val());
            },
        },

        setup() {
            this.once('render remove', function () {
                if (this.isDestroyed) return;
                let el = this.$el.find('.note-editor');
                if (el) {
                    el.popover('destroy');
                    this.isDestroyed = true;
                }
            });

            Dep.prototype.setup.call(this);

            this.detailMaxHeight = this.params.lengthOfCut || this.detailMaxHeight;
            this.seeMoreDisabled = this.seeMoreDisabled || this.params.seeMoreDisabled;
            this.showMoreText = false;
        },

        data() {
            let data = Dep.prototype.data.call(this);
            data.valueWithoutTags = data.value;
            return data;
        },

        removeTags(html) {
            return $('<textarea />').html((html || '').replace(/<(?:.|\n)*?>/gm, ' ').replace(/\s\s+/g, ' ').trim()).text();
        },

        updateTextCounter(text) {
            let maxLength = this.params.maxLength;
            let countBytesInsteadOfCharacters = this.params.countBytesInsteadOfCharacters;
            if (!maxLength) {
                return;
            }

            let textLength = this.getRealLength(text, countBytesInsteadOfCharacters);

            let $el = this.$el.find('.text-length-counter .current-length');

            $el.html(textLength);
            $el.css('color', '');
            if (maxLength < textLength) {
                $el.css('color', 'red');
            }
        },

        getRealLength(text, countBytesInsteadOfCharacters) {
            if (countBytesInsteadOfCharacters) {
                return encodeURI(text).split(/%..|./).length - 1;
            } else {
                return (text ? text.toString().length : 0);
            }
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            /**
             *  Show html in list view
             */
            $('td.cell').each(function () {
                let el = $(this);
                let html = el.html();

                // prepare images
                if (html.search("{img") > 0 && html.search("/img}") > 0) {
                    html = html.replace('{img', '<img').replace('/img}', '>');
                    el.html(html);
                }
            });

            if (this.mode === 'detail' || this.mode === 'list') {
                if ((!this.model.has('isHtml') || this.model.get('isHtml')) && !this.showMoreText && !this.seeMoreDisabled) {
                    this.applyFieldPartHiding(this.name);
                }
            }

            if (this.mode === 'edit') {
                this.updateTextCounter(this.$el.find('.note-editable').html());
            }
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            return this.checkDataForDefaultTagsValue(data, this.name);
        },

        checkDataForDefaultTagsValue(data, field) {
            if (data[field] === '<p><br></p>') {
                data[field] = '';
            }

            if (data[field + 'Plain'] === '<p><br></p>') {
                data[field + 'Plain'] = ''
            }

            return data;
        },

        getValueForDisplay() {
            let text = this.model.get(this.name);
            if (this.mode === 'list') {
                text = this.removeTags(text);
            }

            if (this.mode === 'list' || (this.mode === 'detail' && (this.model.has('isHtml') && !this.model.get('isHtml')))) {
                if (text && !this.showMoreText && !this.seeMoreDisabled) {
                    let isCut = false;

                    if (text.length > this.detailMaxLength) {
                        text = text.substr(0, this.detailMaxLength);
                        isCut = true;
                    }

                    let nlCount = (text.match(/\n/g) || []).length;
                    if (nlCount > this.detailMaxNewLineCount) {
                        let a = text.split('\n').slice(0, this.detailMaxNewLineCount);
                        text = a.join('\n');
                        isCut = true;
                    }

                    if (isCut) {
                        text += ' ...\n[#see-more-text]';
                    }
                }
            }

            return this.sanitizeHtml(text || '');
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.name) === '') {
                    let msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());
                    this.showValidationMessage(msg, '.note-editor');
                    return true;
                }
            }
        },

        applyFieldPartHiding(name) {
            let showMore = $(`<a href="javascript:" data-action="seeMoreText" data-name="${name}">${this.getLanguage().translate('See more')}</a>`);
            if (!this.useIframe) {
                let htmlContainer = this.$el.find(`.html-container[data-name="${name}"]`);
                if (htmlContainer.height() > this.detailMaxHeight) {
                    htmlContainer.parent().append(showMore);
                    htmlContainer.css({
                        maxHeight: this.detailMaxHeight + 'px',
                        overflow: 'hidden',
                        marginBottom: '10px'
                    });
                }
            }
        }
    })
);