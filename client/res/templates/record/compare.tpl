<div class="detail" id="{{id}}">
    <div class="detail-button-container button-container record-buttons clearfix">
        <div class="btn-group pull-left" role="group">
            {{#each buttonList}}{{button name scope=../../entityType label=label style=style hidden=hidden html=html}}{{/each}}
        </div>
        <div class="panel-navigation panel-left pull-left">{{{panelDetailNavigation}}}</div>
        <div class="clearfix"></div>
    </div>

    <div class="row">
        <div class="overview list col-md-12">
            <table class="table full-table table-striped table-fixed table-bordered-inside">
                <thead>
                   <tr>
                       <th></th>
                       <th>
                           {{translate 'yourSystem'}}
                       </th>
                       <th>
                           Connection: {{distantModel._connection}}
                       </th>
                   </tr>

                </thead>
                <tbody>
                {{#each simpleFields}}
                    <tr class="list-row {{#if isLink}} active {{/if}} ">
                        <td>{{translate fieldName scope=../../scope category='fields'}}</td>
                        {{#if isLink}}
                            <td class="cell"><a href="/#{{entity}}/view/{{current.id}}">{{current.name}}</a></td>
                            <td class="cell"><a href="{{distantModel._baseUrl}}/#{{entity}}/view/{{distant.id}}">{{distant.name}}</a></td>
                            <td class="cell" data-name="buttons">
                                <div class="list-row-buttons btn-group pull-right">
                                    <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                                        <span class="fas fa-ellipsis-v"></span>
                                    </button>
                                    <ul class="dropdown-menu pull-right">
                                        <li><a href="#" class="action" data-action="quickCompare" data-scope="{{entity}}" data-id="{{current.id}}">QuickCompare</a></li>
                                    </ul>
                                </div>
                        </td>
                        {{else}}
                            <td class="cell">{{current}}</td>
                            <td class="cell">{{distant}}</td>
                            <td class="cell"></td>
                        {{/if}}

                    </tr>
                {{/each}}
                </tbody>
            </table>
        </div>
    </div>
</div>