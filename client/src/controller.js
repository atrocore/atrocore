

/**
 * Controller. Views, Models and Collections are created here.*/

Espo.define('controller', [], function () {

    var Controller = function (params, injections) {
        this.params = params || {};

        this.baseController = injections.baseController;
        this.viewFactory = injections.viewFactory;
        this.modelFactory = injections.modelFactory;
        this.collectionFactory = injections.collectionFactory;

        this.initialize();

        this._settings = injections.settings || null;
        this._user = injections.user || null;
        this._preferences = injections.preferences || null;
        this._acl = injections.acl || null;
        this._cache = injections.cache || null;
        this._router = injections.router || null;
        this._storage = injections.storage || null;
        this._metadata = injections.metadata || null;
        this._dateTime = injections.dateTime || null;

        this.set('masterRendered', false);
    };

    _.extend(Controller.prototype, {

        defaultAction: 'index',

        name: false,

        params: null,

        viewFactory: null,

        modelFactory: null,

        controllerFactory: null,

        initialize: function () {},

        setRouter: function (router) {
            this._router = router;
        },

        getConfig: function () {
            return this._settings;
        },

        getUser: function () {
            return this._user;
        },

        getPreferences: function () {
            return this._preferences;
        },

        getAcl: function () {
            return this._acl;
        },

        getCache: function () {
            return this._cache;
        },

        getRouter: function () {
            return this._router;
        },

        getStorage: function () {
            return this._storage;
        },

        getMetadata: function () {
            return this._metadata;
        },

        getDateTime: function () {
            return this._dateTime;
        },

        /**
         * Get parameter of all controllers.
         * @param key
         * @return null if doesn't exist.
         */
        get: function (key) {
            if (key in this.params) {
                return this.params[key];
            }
            return null;
        },

        /**
         * Set paramer for all controllers.
         * @param key Name of view.
         * @param value.
         */
        set: function (key, value) {
            this.params[key] = value;
        },

        unset: function (key) {
            delete this.params[key];
        },

        has: function (key) {
            return key in this.params;
        },

        getStoredMainView: function (key) {
            return this.get('storedMainView-' + key);
        },

        hasStoredMainView: function (key) {
            return this.has('storedMainView-' + key);
        },

        clearStoredMainView: function (key) {
            var view = this.getStoredMainView(key);
            if (view) {
                view.remove(true);
            }
            this.unset('storedMainView-' + key);
        },

        storeMainView: function (key, view) {
            this.set('storedMainView-' + key, view);
            view.once('remove', function () {
                this.clearStoredMainView(key);
            }, this);
        },

        checkAccess: function (action) {
            return true;
        },

        handleAccessGlobal: function () {
            if (!this.checkAccessGlobal()) {
                throw new Espo.Exceptions.AccessDenied("Denied access to '" + this.name + "'");
            }
        },

        checkAccessGlobal: function () {
            return true;
        },

        handleCheckAccess: function (action) {
            if (!this.checkAccess(action)) {
                var msg;
                if (action) {
                    msg = "Denied access to action '" + this.name + "#" + action + "'";
                } else {
                    msg = "Denied access to scope '" + this.name + "'";
                }
                throw new Espo.Exceptions.AccessDenied(msg);
            }
        },

        doAction: function (action, options) {
            this.handleAccessGlobal();

            action = action || this.defaultAction;
            var method = action;
            if (!(method in this)) {
                throw new Espo.Exceptions.NotFound("Action '" + this.name + "#" + action + "' is not found");
            }

            var preMethod = 'before' + Espo.Utils.upperCaseFirst(method);
            var postMethod = 'after' + Espo.Utils.upperCaseFirst(method);

            if (preMethod in this) {
                this[preMethod].call(this, options || {});
            }
            this[method].call(this, options || {});
            if (postMethod in this) {
                this[postMethod].call(this, options || {});
            }
        },

        /**
         * Create master view, render it if not rendered and return it.
         * @param {Function} callback Master view will be argument for this.
         */
        master: function (callback) {
            var entire = this.get('entire');
            if (entire) {
                entire.remove();
                this.set('entire', null);
            }
            var master = this.get('master');
            if (!master) {
                var masterView = this.masterView || 'views/site/master';
                this.viewFactory.create(masterView, {el: 'body'}, function (master) {
                    this.set('master', master);
                    if (!this.get('masterRendered')) {
                        master.render(function () {
                            this.set('masterRendered', true);
                            callback.call(this, master);
                        }.bind(this));
                        return;
                    }
                    callback.call(this, master);
                }.bind(this));
            } else {
                callback.call(this, master);
            }
        },

        /**
         * Create main view in master and return it.
         * @param {String} view Name of view.
         * @param {Object} options Options for view.
         * @return {view}
         */
        main: function (view, options, callback, useStored, storedKey) {
            var isCanceled = false;
            this.listenToOnce(this.baseController, 'action', function () {
                isCanceled = true;
            }, this);

            var view = view || 'views/base';
            var master = this.master(function (master) {
                if (isCanceled) return;

                master.showLoadingNotification();
                options = options || {};
                options.el = '#main';

                var process = function (main) {
                    if (isCanceled) return;

                    if (storedKey) {
                        this.storeMainView(storedKey, main);
                    }
                    main.once('render', function () {
                        main.updatePageTitle();
                        master.hideLoadingNotification();
                    });

                    main.listenToOnce(this.baseController, 'action', function () {
                        main.cancelRender();
                        isCanceled = true;
                    }, this);

                    if (master.currentViewKey) {
                        this.set('storedScrollTop-' + master.currentViewKey, $(window).scrollTop());
                        if (this.hasStoredMainView(master.currentViewKey)) {
                            master.unchainView('main');
                        }
                    }
                    master.currentViewKey = storedKey;
                    master.setView('main', main);

                    main.once('after:render', function () {
                        if (useStored && this.has('storedScrollTop-' + storedKey)) {
                            $(window).scrollTop(this.get('storedScrollTop-' + storedKey));
                        } else {
                            $(window).scrollTop(0);
                        }
                    }.bind(this));

                    if (isCanceled) return;

                    if (callback) {
                        callback.call(this, main);
                    } else {
                        main.render();
                    }
                }.bind(this);

                if (useStored) {
                    if (this.hasStoredMainView(storedKey)) {
                        var main = this.getStoredMainView(storedKey);

                        if (!main.lastUrl || main.lastUrl === this.getRouter().getCurrentUrl()) {
                            process(main);
                            if (main && typeof main.applyRoutingParams === 'function') {
                                main.applyRoutingParams(options.params || {});
                            }
                            return;
                        }
                    }
                }
                this.viewFactory.create(view, options, process);
            }.bind(this));
        },

        showLoadingNotification: function () {
            var master = this.get('master');
            if (master) {
                master.showLoadingNotification();
            }
        },

        hideLoadingNotification: function () {
            var master = this.get('master');
            if (master) {
                master.hideLoadingNotification();
            }
        },

        /**
         * Create view in the body tag.
         * @param {String} view Name of view.
         * @param {Object} options Options for view.
         * @return {Espo.View}
         */
        entire: function (view, options, callback) {
            var master = this.get('master');
            if (master) {
                master.remove();
            }
            this.set('master', null);
            this.set('masterRendered', false);
            options = options || {};
            options.el = 'body';
            this.viewFactory.create(view, options, function (view) {
                this.set('entire', view);
                callback(view);
            }.bind(this));
        }

    }, Backbone.Events);

    Controller.extend = Backbone.Router.extend;

    return Controller;
});


