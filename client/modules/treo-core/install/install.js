/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

$(function () {

    let GeneralModel = Backbone.Model.extend({});
    let generalModel = new GeneralModel;

    let MainView = Backbone.View.extend({

        el: $('#main-template'),

        initialize() {
            this.render();
        },

        render() {
            new Promise((resolve, reject) => {
                this.getTranslations().done(translations => {
                    if (translations instanceof Object) {
                        this.model.set({translate: translations});

                        let languageAndLicenseStep = new LanguageAndLicenseStep({
                            model: generalModel,
                            parentEl: this.$el
                        });
                    } else {
                        reject(new Error('Unsuccessful attempt to download translations'))
                    }
                }).error(errorObj => reject(new Error(`${errorObj.statusText} (${errorObj.status})`)))
            }).catch(function (error) {
                this.showBox('alert-danger', error);
                throw error;
            }.bind(this))
        },

        getTranslations() {
            return $.ajax({
                url: 'api/v1/Installer/getTranslations',
                type: 'GET'
            });
        },

        validate(step) {
            let check = true;
            this.requiredFields.forEach(function (field) {
                if (this.model.get(step)[field] === '') {
                    let msg = this.model.get('translate').messages.fieldIsRequired.replace('{field}', this.model.get('translate').fields[field]);
                    mainView.showValidationMessage.call(this, msg, field);
                    check = false;
                }
            }.bind(this));
            return check;
        },

        showValidationMessage: function (message, selector) {
            let isDestroyed = false;

            selector = '#' + selector;

            let $el = this.$el.find(selector);

            $el.popover({
                placement: 'bottom',
                container: 'body',
                content: message,
                trigger: 'manual'
            }).popover('show');

            $el.parent().addClass('has-error');

            $el.one('mousedown click', function () {
                if (isDestroyed) return;

                $el.popover('destroy');
                $el.parent().removeClass('has-error');
                isDestroyed = true;
            });

            this.once('render destroy', function () {
                if ($el) {
                    $el.popover('destroy');
                    $el.parent().removeClass('has-error')
                }
            });
        },

        showMessageBoxText(type, text) {
            if (this._timeout) {
                mainView.hideBox();
                clearTimeout(this._timeout);
            }

            mainView.showBox(type, text);

            this._timeout = setTimeout(function () {
                mainView.hideBox();
            }, 3000);
        },

        showBox(type, text) {
            let msgBox = this.$el.find('.msg-box');
            msgBox.text(text);
            msgBox.addClass(type);
            msgBox.removeClass('hidden');
        },

        hideBox() {
            let msgBox = this.$el.find('.msg-box');
            msgBox.removeClass();
            msgBox.addClass('msg-box alert hidden');
            msgBox.text('');
        },
    });

    let LanguageAndLicenseStep = Backbone.View.extend({

        className: 'main-template container',

        template: _.template($('#language-license').html()),

        events: {
            'change select[name="user-lang"]': 'languageChange',
            'click .next-step': 'nextStep'
        },

        initialize(options) {
            this.parentEl = options.parentEl;
            this.getLicenseAndLanguages().done(function (data) {
                this.model.set({licenseAndLanguages: data});
                this.render();
            }.bind(this));
        },

        render() {
            this.$el.html(this.template(this.model.toJSON()));
            this.parentEl.append(this.$el);
        },

        getLicenseAndLanguages() {
            return $.ajax({
                url: 'api/v1/Installer/getLicenseAndLanguages',
                type: 'GET'
            });
        },

        nextStep() {
            if (this.$el.find('#license-agree').is(':checked')) {
                this.remove();
                let dbConnectSettings = new DbConnectSettings({model: generalModel, parentEl: this.parentEl});
            } else {
                mainView.showMessageBoxText.call(this, 'alert-danger', this.model.get('translate').messages.youMustAgreeToTheLicenseAgreement);
            }
        },

        languageChange() {
            let data = this.model.get('licenseAndLanguages');
            _.extend(data, {language: this.$el.find('select[name="user-lang"]').val() || ''});
            this.model.set('licenseAndLanguages', data);
            let dataToSave = {
                language: this.model.get('licenseAndLanguages').language
            };
            $.ajax({
                url: 'api/v1/Installer/setLanguage',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(dataToSave)
            }).done(function (data) {
                if (data.status) {
                    mainView.getTranslations().done(function (translations) {
                        this.model.set({translate: translations});
                        this.render();
                    }.bind(this));
                } else {
                    mainView.showMessageBoxText.call(this, 'alert-danger', data.message);
                }
            }.bind(this));
        },
    });

    let DbConnectSettings = Backbone.View.extend({

        className: 'main-template container',

        template: _.template($('#db-connect').html()),

        requiredFields: ['host', 'dbname', 'user'],

        events: {
            'click .test-db-connection': 'testDbConnection',
            'click .back-step': 'backStep',
            'click .next-step': 'nextStep',
            'focusout .modal-body input.form-control': 'setFieldsValuesToModel'
        },

        initialize(options) {
            this.parentEl = options.parentEl;
            this.getDefaultDbSettings().done(function (data) {
                if (!this.model.has('dbSettings')) {
                    this.model.set({dbSettings: data});
                }
                this.render();
            }.bind(this));
        },

        render() {
            this.$el.html(this.template(this.model.toJSON()));
            this.parentEl.append(this.$el);
        },

        getDefaultDbSettings() {
            return $.ajax({
                url: 'api/v1/Installer/getDefaultDbSettings',
                type: 'GET'
            });
        },

        setFieldsValuesToModel() {
            let data = {
                driver: this.$el.find('#driver').val(),
                host: this.$el.find('#host').val(),
                dbname: this.$el.find('#dbname').val(),
                user: this.$el.find('#user').val(),
                password: this.$el.find('#password').val(),
                port: this.$el.find('#port').val()
            };
            this.model.set({dbSettings: data});
        },

        testDbConnection() {
            if (mainView.validate.call(this, 'dbSettings')) {
                this.checkDbConnect().done(function (data) {
                    if (data.status) {
                        mainView.showMessageBoxText.call(this, 'alert-success', this.model.get('translate').messages.connectionSuccessful);
                    } else {
                        mainView.showMessageBoxText.call(this, 'alert-danger', data.message);
                    }
                }.bind(this));
            }
        },

        checkDbConnect() {
            let data = {
                driver: this.model.get('dbSettings').driver,
                host: this.model.get('dbSettings').host,
                dbname: this.model.get('dbSettings').dbname,
                user: this.model.get('dbSettings').user,
                password: this.model.get('dbSettings').password,
                port: this.model.get('dbSettings').port
            };
            return $.ajax({
                url: 'api/v1/Installer/checkDbConnect',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data)
            });
        },

        backStep() {
            this.trigger('destroy');
            this.remove();
            let languageAndLicenseStep = new LanguageAndLicenseStep({model: generalModel, parentEl: this.parentEl});
        },

        nextStep() {
            if (mainView.validate.call(this, 'dbSettings')) {
                this.setDbSettings().done(function (data) {
                    if (data.status) {
                        this.remove();
                        let requiredSettings = new RequiredSettings({model: generalModel, parentEl: this.parentEl});
                    } else {
                        mainView.showMessageBoxText.call(this, 'alert-danger', data.message);
                    }
                }.bind(this));
            }
        },

        setDbSettings() {
            let data = {
                driver: this.model.get('dbSettings').driver,
                host: this.model.get('dbSettings').host,
                dbname: this.model.get('dbSettings').dbname,
                user: this.model.get('dbSettings').user,
                password: this.model.get('dbSettings').password,
                port: this.model.get('dbSettings').port
            };
            return $.ajax({
                url: 'api/v1/Installer/setDbSettings',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data)
            });
        },

    });

    let RequiredSettings = Backbone.View.extend({

        className: 'main-template container',

        template: _.template($('#required-settings').html()),

        requiresChecked: false,

        events: {
            'click .re-check-settings': 'checkSettings',
            'click .back-step': 'backStep',
            'click .next-step': 'nextStep'
        },

        initialize(options) {
            this.parentEl = options.parentEl;
            this.checkSettings();
        },

        render() {
            this.$el.html(this.template(this.model.toJSON()));
            this.parentEl.append(this.$el);
        },

        backStep() {
            this.trigger('destroy');
            this.remove();
            let dbConnectSettings = new DbConnectSettings({model: generalModel, parentEl: this.parentEl});
        },

        nextStep() {
            if (this.requiresChecked) {
                this.remove();
                let adminCreation = new AdminCreation({model: generalModel, parentEl: this.parentEl});
            } else {
                mainView.showMessageBoxText.call(this, 'alert-danger', this.model.get('translate').messages.pleaseConfigureYourSystemToStart);
            }
        },

        checkSettings() {
            this.$el.find('button.re-check-settings').addClass('disabled').attr('disabled', 'disabled');
            this.getRequiredsList().done(function (data) {
                let requiredSettings = data || [];
                this.requiresChecked = requiredSettings.every(item => item.isValid);
                this.model.set({requiredSettings: {list: requiredSettings, requiresChecked: this.requiresChecked}});
                this.render();
            }.bind(this));
        },

        getRequiredsList() {
            return $.ajax({
                url: 'api/v1/Installer/getRequiredsList',
                type: 'GET'
            });
        }
    });

    let AdminCreation = Backbone.View.extend({

        className: 'main-template container',

        template: _.template($('#admin-creation').html()),

        requiredFields: ['username', 'password', 'confirmPassword'],

        events: {
            'click .back-step': 'backStep',
            'click .next-step': 'nextStep',
            'focusout .modal-body input.form-control': 'setFieldsValuesToModel'
        },

        initialize(options) {
            this.parentEl = options.parentEl;
            if (!this.model.has('adminSettings')) {
                this.model.set({
                    adminSettings: {
                        username: '',
                        password: '',
                        confirmPassword: ''
                    }
                });
            }
            this.render();
        },

        render() {
            this.$el.html(this.template(this.model.toJSON()));
            this.parentEl.append(this.$el);
        },

        setFieldsValuesToModel() {
            let data = {
                username: this.$el.find('#username').val(),
                password: this.$el.find('#password').val(),
                confirmPassword: this.$el.find('#confirmPassword').val()
            };
            this.model.set({adminSettings: data});
        },

        backStep() {
            this.trigger('destroy');
            this.remove();
            let requiredSettings = new RequiredSettings({model: generalModel, parentEl: this.parentEl});
        },

        nextStep() {
            if (mainView.validate.call(this, 'adminSettings')) {
                this.disabledStepButtons(true);

                this.setAdminSettings().done(function (data) {
                    if (data.status) {
                        window.location.reload();
                    } else {
                        this.disabledStepButtons(false);
                        mainView.showMessageBoxText.call(this, 'alert-danger', data.message);
                    }
                }.bind(this));
            }
        },

        setAdminSettings() {
            let data = {
                username: this.model.get('adminSettings').username,
                password: this.model.get('adminSettings').password,
                confirmPassword: this.model.get('adminSettings').confirmPassword,
                reportingEnabled: this.$el.find('#reporting-enabled').is(':checked')
            };
            return $.ajax({
                url: 'api/v1/Installer/createAdmin',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(data)
            });
        },

        disabledStepButtons(attr) {
            this.$el.find('.back-step').attr('disabled', attr);
            this.$el.find('.next-step').attr('disabled', attr);
            if (attr) {
                this.$el.find('.loader').removeClass('hidden');
            } else {
                this.$el.find('.loader').addClass('hidden');
            }
        }
    });

    let mainView = new MainView({model: generalModel});
});