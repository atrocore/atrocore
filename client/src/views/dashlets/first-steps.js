Espo.define('views/dashlets/first-steps', 'views/dashlets/abstract/base', function (Dep) {

    return Dep.extend({

        name: 'FirstSteps',

        template: 'dashlets/record-list/first-steps',

        events: {
            'click a.card': function (e) {
                const step = e.currentTarget.getAttribute('data-step');
                let completedSteps = this.optionsData.completedSteps;
                if (!Array.isArray(completedSteps)) {
                    completedSteps = [];
                }
                completedSteps.push(step);

                this.optionsData.completedSteps = completedSteps;

                if (this.id) {
                    const options = this.getPreferences().get('dashletsOptions') || {};
                    options[this.id] = this.optionsData;

                    this.getPreferences().save({
                        dashletsOptions: options
                    }, {patch: true});

                    this.reRender()
                }
            }
        },

        data: function () {
            return {
                items: this.getItems()
            }
        },

        getItems: function () {
            const steps = this.getMetadata().get(['app', 'firstStepsDashletData']);
            const langTitle = this.getLanguage().translate('title', 'firstStepsDashlet', 'Global');
            const langDescription = this.getLanguage().translate('description', 'firstStepsDashlet', 'Global');

            return steps.map(item => ({
                name: item.name,
                title: langTitle[item.name] || item.name,
                description: langDescription[item.name] || null,
                icon: item.icon || 'ph ph-notebook',
                url: item.url || "#",
                urlNewTab: item.urlNewTab || false,
                completed: (this.optionsData.completedSteps || []).includes(item.name) || false,
            }));
        }

    });
});


