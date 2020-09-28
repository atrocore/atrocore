

Espo.define('pre-loader', [], function () {

    var PreLoader = function (cache, viewFactory, basePath) {
        this.cache = cache;
        this.viewFactory = viewFactory;
        this.basePath = basePath || '';
    }

    _.extend(PreLoader.prototype, {

        configUrl: 'client/cfg/pre-load.json',

        cache: null,

        viewFactory: null,

        load: function (callback, app) {

            var bar = $('<div class="progress pre-loading"><div class="progress-bar" id="loading-progress-bar" role="progressbar" aria-valuenow="0" style="width: 0%;"></div></div>').prependTo('body');;
            bar = bar.children();
            bar.css({
                'transition': 'width .1s linear',
                '-webkit-transition': 'width .1s linear'
            });

            var self = this;

            var count = 0;
            var countLoaded = 0;
            var classesLoaded = 0;
            var templatesLoaded = 0;
            var layoutTypesLoaded = 0;

            var updateBar = function () {
                var percents = countLoaded / count * 100;
                bar.css('width', percents + '%').attr('aria-valuenow', percents);
            }

            var checkIfReady = function () {
                if (countLoaded >= count) {
                    clearInterval(timer);
                    callback.call(app, app);
                }
            };
            var timer = setInterval(checkIfReady, 100);

            var load = function (data) {
                data.classes = data.classes || [];
                data.templates = data.templates || [];
                data.layoutTypes = data.layoutTypes || [];

                var d = [];
                data.classes.forEach(function (item) {
                    if (item != 'views/fields/enum') {
                        d.push(item); // TODO remove this huck
                    }
                }, this);
                data.classes = d;

                count = data.templates.length + data.layoutTypes.length+ data.classes.length;

                var loadTemplates = function () {
                    data.templates.forEach(function (name) {
                        self.viewFactory._loader.load('template', name, function () {
                            layoutTypesLoaded++;
                            countLoaded++;
                            updateBar();
                        });
                    });
                }
                var loadLayoutTypes = function () {
                    data.layoutTypes.forEach(function (name) {
                        self.viewFactory._loader.load('layoutTemplate', name, function () {
                            layoutTypesLoaded++;
                            countLoaded++;
                            updateBar();
                        });
                    });
                }
                var loadClasses = function () {
                    data.classes.forEach(function (name) {
                        Espo.loader.require(name, function () {
                            classesLoaded++;
                            countLoaded++;
                            updateBar();
                        });
                    });
                }

                loadTemplates();
                loadLayoutTypes();
                loadClasses();
            };

            $.ajax({
                url: this.basePath + this.configUrl,
                dataType: 'json',
                local: true,
                success: function (data) {
                    load(data);
                }
            });
        }
    });

    return PreLoader;

});
