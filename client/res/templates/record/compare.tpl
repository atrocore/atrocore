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
                           {{translate 'currentModel' category='Connector' scope='labels'}}
                       </th>
                       <th>
                           {{translate 'otherFrom' category='Connector' scope='labels'}} {{distantModel._connection}}
                       </th>
                       <th width="25"></th>
                   </tr>

                </thead>
                <tbody>
                    {{#each fieldsArr}}
                       {{#if isField }}
                        <tr class="list-row {{#if  different}} danger {{/if}}">
                            <td class="cell">{{translate label scope=../scope category='fields'}}</td>
                            <td class="cell ">
                                <div class="field">{{{var current ../this}}}</div>
                            </td>
                            <td class="cell">
                                <div class="field">{{{var other ../this}}}</div>
                            </td>
                            {{#if isLink }}
                                <td class="cell" data-name="buttons">
                                    <div class="list-row-buttons btn-group pull-right">
                                        <button type="button" class="btn btn-link btn-sm dropdown-toggle" data-toggle="dropdown">
                                            <span class="fas fa-ellipsis-v"></span>
                                        </button>
                                        <ul class="dropdown-menu pull-right">
                                            {{#if isLinkMultiple }}
                                            <li> <a class="disabled panel-title">  {{translate 'QuickCompare' scope='Connector'}}</a></li>
                                                {{#each values }}
                                                    <li>
                                                        <a href="#" class="action" data-action="quickCompare"
                                                           data-scope="{{../foreignScope}}"
                                                           data-id="{{id}}">
                                                           {{ name }}
                                                        </a>
                                                    </li>
                                                {{/each}}
                                            {{else}}
                                            <li><a href="#" class="action" data-action="quickCompare" data-scope="{{foreignScope}}" data-id="{{foreignId}}">QuickCompare</a></li>
                                            {{/if}}
                                        </ul>
                                    </div>
                                </td>
                            {{else}}
                             <td></td>
                            {{/if}}
                        </tr>

                       {{/if}}
                    {{/each}}
                </tbody>
            </table>
        </div>
    </div>
</div>