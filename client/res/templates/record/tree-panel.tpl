<button type="button" class="btn btn-default collapse-panel" data-action="collapsePanel">
    <span class="toggle-icon-left fas fa-angle-left"></span>
    <span class="toggle-icon-right fas fa-angle-right hidden"></span>
</button>
<div class="category-panel">
    <div class="panel-group text-center">
        <div class="btn-group">
            <a href="/#{{scope}}" class="btn btn-default active reset-tree-filter">{{translate 'Unset Selection'}}</a>
        </div>
    </div>
    <div class="panel-group category-search">
        {{{categorySearch}}}
    </div>
    {{#if scopesEnum}}
    <div class="panel-group scopes-enum">
        {{{scopesEnum}}}
    </div>
    {{/if}}
    <div class="panel-group category-tree"></div>
    <div class="category-panel-resizer"></div>
</div>
