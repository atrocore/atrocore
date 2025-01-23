<div class="navbar navbar-inverse" role="navigation" data-orientation="{{#if navbarIsVertical}}vertical{{else}}horizontal{{/if}}">
    <div class="navbar-header">
        <button type="button" class="navbar-toggle">
            <span class="fas fa-bars"></span>
        </button>
        <a class="navbar-brand nav-link" href="#"><img src="{{logoSrc}}" class="logo"><span class="home-icon fas fa-home" title="{{translate 'homepage'}}"></span></a>
        <div class="navbar-header-inner pull-right">
            {{#if globalSearch}}
            <button type="button" class="search-toggle pull-left visible-xs">
                <span class="fa fa-search"></span>
            </button>
            {{/if}}
            <ul class="visible-xs header-right pull-left">
                {{#if lastViewed}}
                <li class="last-viewed-badge-container">
                    {{{lastViewedBadgeRight}}}
                </li>
                {{/if}}
                {{#if hasJM}}<li class="dropdown queue-badge-container"></li>{{/if}}
                <li class="notifications-badge-container ">
                    {{{notificationsBadge}}}
                </li>
            </ul>
            <div class="dropdown menu-container visible-xs pull-left">
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><span class="fas fa-user"></span></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</a></li>
                    {{else}}
                    <li class="divider"></li>
                    {{/unless}}
                    {{/each}}
                </ul>
            </div>
        </div>
    </div>

    <div class="menu">
        <ul class="nav navbar-nav tabs">
            {{#each tabDefsList}}
            {{#if group}}
            <li class="dropdown more more-group tab">
                <a id="nav-more-tabs-dropdown-{{id}}" class="dropdown-toggle more-group-name" data-toggle="dropdown" href="#" {{#if color}} style="border-color: {{color}}"{{/if}}>
                    <span class="short-label" title="{{label}}"{{#if color}} style="color: {{color}}"{{/if}}>
                        {{#if iconClass}}
                        <span class="{{iconClass}}"></span>
                        {{else}}
                        {{#if colorIconClass}}
                        <span class="{{colorIconClass}}" style="color: {{color}}"></span>
                        {{/if}}
                        <span class="short-label-text">{{shortLabel}}</span>
                        {{/if}}
                    </span>
                    <span class="full-label">{{label}} <span class="fas fa-angle-down"></span></span>
                </a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-more-tabs-dropdown-{{id}}">
                    {{#each items}}
                    <li data-name="{{name}}" class="in-more tab">
                        <a href="{{link}}" class="nav-link"{{#if color}} style="border-color: {{color}}"{{/if}}>
                            <span class="short-label" title="{{label}}"{{#if color}} style="color: {{color}}"{{/if}}>
                                {{#if iconClass}}
                                <span class="{{iconClass}}"></span>
                                {{else}}
                                {{#if colorIconClass}}
                                <span class="{{colorIconClass}}" style="color: {{color}}"></span>
                                {{/if}}
                                <span class="short-label-text">{{shortLabel}}</span>
                                {{/if}}
                            </span>
                            <span class="full-label">{{label}}</span>
                        </a>
                    </li>
                    {{/each}}
                </ul>
            </li>
            {{else}}
            <li data-name="{{name}}" class="not-in-more tab">
                <a href="{{link}}" class="nav-link"{{#if color}} style="border-color: {{color}}"{{/if}}>
                    <span class="full-label">{{label}}</span>
                    <span class="short-label" title="{{label}}"{{#if color}} style="color: {{color}}"{{/if}}>
                    {{#if iconClass}}
                        <span class="{{iconClass}}"></span>
                        {{else}}
                        {{#if colorIconClass}}
                        <span class="{{colorIconClass}}" style="color: {{color}}"></span>
                        {{/if}}
                        <span class="short-label-text">{{shortLabel}}</span>
                        {{/if}}
                    </span>
                </a>
            </li>
            {{/if}}
            {{/each}}
        </ul>
        <footer>{{{footer}}}</footer>
    </div>

    <div class="collapse navbar-collapse navbar-body">
        <ul class="nav navbar-nav navbar-right">
            <li class="dropdown menu-container hidden-xs">
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><span class="fas fa-user"></span></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" {{#if targetBlank}} target="_blank" {{/if}} class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{label}}{{/if}}</a></li>
                    {{else}}
                    <li class="divider"></li>
                    {{/unless}}
                    {{/each}}
                </ul>
            </li>
            {{#unless hideFeedbackIcon}}
            <li class="openFeedbackDialog hidden-xs">
                <a href="javascript:" class="action notifications-button" data-action="openFeedbackModal" title="{{translate 'Provide Feedback'}}">
                    <i class="fas fa-comment"></i>
                </a>
            </li>
            {{/unless}}
            {{#if lastViewed}}
            <li class="dropdown hidden-xs last-viewed-badge-container">
                {{{lastViewedBadge}}}
            </li>
            {{/if}}
            {{#if showBookmarked }}
            <li class="dropdown hidden-xs bookmark-badge-container">
                {{{bookmarkBadge}}}
            </li>
            {{/if}}
            {{#if enableQuickCreate}}
            <li class="dropdown hidden-xs quick-create-container hidden-xs">
                <a id="nav-quick-create-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Create'}}"><i class="fas fa-plus"></i></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-quick-create-dropdown">
                    <li class="dropdown-header"><span class="panel-heading-title">{{translate 'quickCreate'}}</span></li>
                    {{#each quickCreateList}}
                    <li><a href="#{{./this}}/create" data-name="{{./this}}" data-action="quick-create">{{translate this category='scopeNames'}}</a></li>
                    {{/each}}
                </ul>
            </li>
            {{/if}}
            {{#if hasJM}}<li id='qqq' class="dropdown queue-badge-container hidden-xs"></li>{{/if}}
            <li class="dropdown notifications-badge-container hidden-xs">
                {{{notificationsBadge}}}
            </li>

            {{#if globalSearch}}
            <li class="nav navbar-nav navbar-form global-search-container">
                {{{globalSearch}}}
            </li>
            {{/if}}
        </ul>

    </div>
</div>
