/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore UG (haftungsbeschränkt).
 *
 * AtroCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AtroCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "AtroCore" word.
 */

Espo.define('treo-core:views/fields/wysiwyg', 'class-replace!treo-core:views/fields/wysiwyg',
    Dep => Dep.extend({

        listTemplate: 'treo-core:fields/wysiwyg/list',

        detailTemplate: 'treo-core:fields/wysiwyg/detail',

        detailMaxHeight: 400,

        showMoreText: false,

        showMoreDisabled: false,

        events: {
            'click a[data-action="seeMoreText"]': function (e) {
                this.showMoreText = true;
                this.reRender();
            }
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

            this.detailMaxHeight = this.params.displayedHeight || this.detailMaxHeight;
            this.showMoreDisabled = this.showMoreDisabled || this.params.showMoreDisabled;
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
                if ((!this.model.has('isHtml') || this.model.get('isHtml')) && !this.showMoreText && !this.showMoreDisabled) {
                    this.applyFieldPartHiding(this.name);
                }
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
                if (text && !this.showMoreText && !this.showMoreDisabled) {
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