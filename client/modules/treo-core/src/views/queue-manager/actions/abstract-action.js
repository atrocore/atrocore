

Espo.define('treo-core:views/queue-manager/actions/abstract-action', 'view',
    Dep => Dep.extend({

        template: 'treo-core:queue-manager/actions/abstract-action',

        buttonLabel: '',

        actionData: {},

        disabled: false,

        events: {
            'click [data-action="runAction"]': function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (this.canRun()) {
                    this.runAction();
                }
            },
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.actionData = this.options.actionData || this.actionData;
        },

        data() {
            return {
                buttonLabel: this.buttonLabel,
                disabled: this.disabled
            };
        },

        runAction() {
            //run action
        },

        canRun() {
            return !this.disabled;
        }

    })
);

