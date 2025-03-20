<button type="button" class="btn btn-default collapse-panel" data-action="collapsePanel">
    <svg class="icon toggle-icon-left"><use href="client/img/icons/icons.svg#angle-left"></use></svg>
    <svg class="icon toggle-icon-right hidden"><use href="client/img/icons/icons.svg#angle-right"></use></svg>
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
