<div class="dropdown-toggle form-control selected-element" data-toggle="dropdown" data-name="{{name}}"><span>{{selected}}</span></div>
<ul class="dropdown-menu dropdown-enum-menu dropdown-menu-right">
    {{#each options}}
    <li><a href="javascript:" data-name="{{name}}" class="action" data-action="saveFilter">{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</a></li>
    {{/each}}
</ul>