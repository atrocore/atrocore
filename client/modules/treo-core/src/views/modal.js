

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

Espo.define('treo-core:views/modal', 'class-replace!treo-core:views/modal', function (Dep) {

   return Dep.extend({

       init() {
           Dep.prototype.init.call(this);

           $(window).on('keydown', e => {
               if (e.keyCode === 69 && e.ctrlKey) {
                   e.preventDefault();
               }
               if (e.keyCode === 83 && e.ctrlKey) {
                   e.preventDefault();
                   if (typeof this.actionSave === 'function') {
                       this.actionSave();
                   }
               }
           });

           this.listenTo(this, 'after:render', () => {
               const headerHeight = this.$el.find('header.modal-header').outerHeight();
               const footerHeight = this.$el.find('footer.modal-footer').outerHeight();

               let diffHeight = headerHeight + footerHeight;

               if (!this.dialog.options.fullHeight) {
                   diffHeight = diffHeight + this.dialog.options.bodyDiffHeight;
               }

               const adjustHeight = () => {
                   const windowHeight = window.innerHeight;
                   const windowWidth = window.innerWidth;
                   const cssParams = {
                       overflow: 'auto'
                   };
                   if (!this.dialog.options.fullHeight && windowHeight < 512) {
                       cssParams.maxHeight = 'none';
                       cssParams.height = 'none';
                   } else if (this.dialog.options.fullHeight) {
                       cssParams.height = (windowHeight - diffHeight) + 'px';
                   } else {
                       if (windowWidth <= this.dialog.options.screenWidthXs) {
                           cssParams.maxHeight = 'none';
                       } else {
                           cssParams.maxHeight = (windowHeight - diffHeight) + 'px';
                       }
                   }

                   this.$el.find('div.modal-body').css(cssParams);
               };
               $(window).off('resize.adjust-modal-height').on('resize.adjust-modal-height', adjustHeight);
               adjustHeight();
           });
       },

   });
});