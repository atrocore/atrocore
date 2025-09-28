
<div class="group-head" data-level="{{level}}">
    {{#ifNotEqual level 0}}
    <a class="pull-right" href="javascript:" data-action="remove"><i class="ph ph-x"></i></a>
    {{/ifNotEqual}}
    {{#ifNotEqual level 0}}
    <div>(</div>
    {{else}}
    &nbsp;
    {{/ifNotEqual}}
</div>

<div class="item-list" data-level="{{level}}">
{{#each viewDataList}}
    <div data-view-key="{{key}}">{{{var key ../this}}}</div>
    <div class="group-operator" data-view-ref-key="{{key}}">{{translate ../groupOperator category='logicalOperators' scope='Admin'}}</div>
{{/each}}
</div>

<div class="group-bottom" data-level="{{level}}">
    <div class="btn-group">
        <a class="dropdown-toggle small" href="javascript:" data-toggle="dropdown">{{translate groupOperator category='logicalOperators' scope='Admin'}} <span class="ph ph-plus"></span></a>
        <ul class="dropdown-menu">
            <li><a href="javascript:" data-action="addField">{{translate 'Field' scope='DynamicLogic'}}</a></li>
            {{#if hasAttributes }}
            <li><a href="javascript:" data-action="addAttribute">{{translate 'Attribute' scope='DynamicLogic'}}</a></li>
            {{/if}}
            <li><a href="javascript:" data-action="addCurrentUser">{{translate 'currentUser' scope='DynamicLogic'}} (...)</a></li>
            <li><a href="javascript:" data-action="addAnd">(... {{translate 'and' category='logicalOperators' scope='Admin'}} ...)</a></li>
            <li><a href="javascript:" data-action="addOr">(... {{translate 'or' category='logicalOperators' scope='Admin'}} ...)</a></li>
            <li><a href="javascript:" data-action="addNot">{{translate 'not' category='logicalOperators' scope='Admin'}} (...)</a></li>
        </ul>
    </div>
</div>

{{#ifNotEqual level 0}}
<div>)</div>
{{/ifNotEqual}}
