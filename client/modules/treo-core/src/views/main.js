

Espo.define('treo-core:views/main', 'class-replace!treo-core:views/main', function (Dep) {

    return Dep.extend({

        setupFinal() {
            Dep.prototype.setupFinal.call(this);

            this.bindFixedHeaderOnScroll();
        },

        buildHeaderHtml: function (arr) {
            var a = [];
            arr.forEach(function (item) {
                a.push('<span>' + item + '</span>');
            }, this);

            return '<div class="header-breadcrumbs">' + a.join('<span class="breadcrumb-separator"> &rsaquo; </span>') + '</div>';
        },

        bindFixedHeaderOnScroll() {
            let $window = $(window);
            this.listenToOnce(this, 'remove', () => {
                $window.off('scroll.fixed-header')
            });
            this.listenTo(this, 'after:render', () => {
                $window.off('scroll.fixed-header');
                $window.on('scroll.fixed-header', () => {
                    let scrollTop = $window.scrollTop();
                    let header = this.$el.find('.header-breadcrumbs');
                    let navBarRight = $('#header .navbar-right');
                    let width = $('#header ul.navbar-right > li').get().reduce((prev, curr) => {
                        return prev - $(curr).outerWidth()
                    }, navBarRight.outerWidth() - 30);
                    if (scrollTop > this.$el.find('.page-header').outerHeight() && !$('#header .navbar .menu').hasClass('open-menu')) {
                        header.addClass('fixed-header-breadcrumbs')
                            .css('width', width + 'px');
                    } else {
                        header.removeClass('fixed-header-breadcrumbs')
                            .css('width', 'auto');
                    }
                });
            });
        }

    });
});