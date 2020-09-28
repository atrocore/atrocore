

Espo.define('views/site/master', 'view', function (Dep) {

    return Dep.extend({

        template: 'site/master',

        views: {
            header: {
                id: 'header',
                view: 'views/site/header'
            },
            main: {
                id: 'main',
                view: false,
            },
            footer: {
                el: 'body > footer',
                view: 'views/site/footer'
            },
        },

        showLoadingNotification: function () {
            this.notify('Loading...');
        },

        hideLoadingNotification: function () {
            this.notify(false);
        },

        afterRender: function () {
            var footerView = this.getView('footer');
            if (footerView) {
                var html = footerView.$el.html() || '';
                if ((html.match(/espocrm/gi) || []).length < 2) {
                    var text = 'PHAgY2xhc3M9ImNyZWRpdCBzbWFsbCI+JmNvcHk7IDxhIGhyZWY9Imh0dHA6Ly93d3cuZXNwb2NybS5jb20iPkVzcG9DUk08L2E+PC9wPg==';
                    if (typeof window.atob === "function") {
                        var decText = window.atob(text);
                    } else if (typeof atob === "function") {
                        var decText = atob(text);
                    }

                    if (decText) {
                        footerView.$el.html(decText);
                    }
                }
            }
        },
    });
});


