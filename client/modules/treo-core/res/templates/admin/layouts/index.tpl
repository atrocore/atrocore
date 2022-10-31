<div class="page-header">
    <h3>
        <div class="header-breadcrumbs fixed-header-breadcrumbs">
            <div class="breadcrumbs-wrapper">
                <a href="#Admin">{{translate 'Administration'}}</a>{{translate 'Layout Manager' scope='Admin'}}
            </div>
        </div>
        <div class="header-title">{{translate 'Layout Manager' scope='Admin'}}</div>
    </h3>

    <button style="margin: 10px 7px 10px 5px" class="btn btn-default action" data-action="resetAllToDefault" type="button">{{translate 'resetAllToDefault'}}</button>
</div>

<div class="row" style="margin-left: -3px">
    <div id="layouts-menu" class="col-sm-3">
        <div class="panel-group" id="layout-accordion">
            {{#each layoutScopeDataList}}
            <div class="panel panel-default">
                <div class="panel-heading">
                    <a class="accordion-toggle" data-parent="#layout-accordion" data-toggle="collapse" href="#collapse-{{toDom scope}}">{{translate scope category='scopeNamesPlural'}}</a>
                </div>
                <div id="collapse-{{toDom scope}}" class="panel-collapse collapse{{#ifEqual scope ../scope}} in{{/ifEqual}}">
                    <div class="panel-body">
                        <ul class="list-unstyled" style="overflow-x: hidden;";>
                            {{#each typeList}}
                            <li>
                                <button style="display: block;" class="layout-link btn btn-link" data-type="{{./this}}" data-scope="{{../scope}}">{{translate this scope='Admin' category='layouts'}}</button>
                            </li>
                            {{/each}}
                        </ul>
                    </div>
                </div>
            </div>
            {{/each}}
        </div>
    </div>

    <div id="layouts-panel" class="col-sm-9">
        <h4 id="layout-header" style="margin-top: 0px;"></h4>
        <div id="layout-content">
            {{{content}}}
        </div>
    </div>
</div>




