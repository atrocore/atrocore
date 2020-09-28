

Espo.define('treo-core:views/record/panel-navigation', 'view',
    Dep => Dep.extend({

        template: 'treo-core:record/panel-navigation',

        panelList: [],

        events: {
            'click [data-action="scrollToPanel"]'(e) {
                let target = e.currentTarget;
                this.actionScrollToPanel($(target).data('name'));
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.setPanelList();

            this.listenTo(this.model, 'change', () => {
                this.setPanelList();
                this.reRender();
            });
        },

        setPanelList() {
            this.panelList = this.options.panelList.filter(panel => !panel.hidden);
        },

        data() {
            return {
                panelList: this.panelList
            };
        },

        actionScrollToPanel(name) {
            if (!name) {
                return;
            }
            let offset = this.getParentView().$el.find(`.panel[data-name="${name}"]`).offset();
            let navbarHeight = $('#navbar .navbar-right').innerHeight() || 0;
            let navigationHeight = $('.record-buttons').innerHeight() || 0;
            $(window).scrollTop(offset.top - navbarHeight - navigationHeight);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.panelList.length) {
                let subtraction = 9;
                let prev = this.$el.prev('.pull-left');
                if (prev.size()) {
                    subtraction += prev.outerWidth();
                }
                let next = this.$el.next('.pull-right');
                if (next.size()) {
                    subtraction += next.outerWidth();
                }
                this.$el.css({width: `calc(100% - ${subtraction}px)`});
            }
        }

    })
);