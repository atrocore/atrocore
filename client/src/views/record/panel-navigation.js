/*
 * This file is part of EspoCRM and/or AtroCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * AtroCore is EspoCRM-based Open Source application.
 * Copyright (C) 2020 AtroCore GmbH.
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

Espo.define('views/record/panel-navigation', 'view',
    Dep => Dep.extend({

        template: 'record/panel-navigation',

        panelList: [],

        events: {
            'click [data-action="scrollToPanel"]'(e) {
                let name = $(e.currentTarget).data('name')
                if(this.isPanelClosed(name)){
                    let panel = this.options.panelList.filter(p => p.name === name)[0]
                    Backbone.trigger('create-bottom-panel', panel)
                    this.listenTo(Backbone,'after:create-bottom-panel',function(panel){
                       setTimeout(() =>  this.actionScrollToPanel(panel.name), 100)
                    })
                }else{
                    this.actionScrollToPanel(name);
                }
            }
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.scope = this.options.scope ?? this.scope
            this.setPanelList();

            this.listenTo(this.model, 'change', () => {
                this.setPanelList();
                this.reRender();
            });
        },

        setPanelList() {
            this.panelList = this.options.panelList
                .filter(panel => {
                    if (panel.name === 'panel-0') {
                        return true;
                    }

                    if(this.isPanelClosed(panel.name)) {
                        return true;
                    }

                    const panelElement = document.querySelector(`div.panel[data-name="${panel.name}"]`);

                    return panelElement && panelElement.style.display !== 'none' && !$(panelElement).hasClass('hidden');
                });
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
            const panel = this.getParentView().$el.find(`.panel[data-name="${name}"]`);
            if (panel.size() > 0) {
                panel.get(0).scrollIntoView();
            }
        },

        isPanelClosed(name){
            let preferences =  this.getPreferences().get('closedPanelOptions') ?? {};
            let scopePreferences = preferences[this.scope] ?? []
            return (scopePreferences['closed'] || []).includes(name)
        },
    })
);
