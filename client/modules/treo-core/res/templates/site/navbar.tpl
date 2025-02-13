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
                <li class="notifications-badge-container">
                    {{{notificationsBadge}}}
                </li>
                {{#if lastViewed}}
                <li class="last-viewed-badge-container">
                    {{{lastViewedBadgeRight}}}
                </li>
                {{/if}}
                {{#if hasJM}}<li class="dropdown queue-badge-container"></li>{{/if}}
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

    <div class="menu not-collapsed">
        <ul class="nav navbar-nav tabs">
            <li class="header">
                <span>{{translate "Navigation Menu"}}</span>
                {{#if canConfigureMenu}}
                <span data-action="configureMenu" class="fas fa-cog cursor-pointer" style="font-size: 1em;"></span>
                {{/if}}
            </li>
            {{#each tabDefsList}}
            {{#if group}}
            <li class="dropdown more more-group tab">
                <a id="nav-more-tabs-dropdown-{{id}}" class="dropdown-toggle more-group-name" data-toggle="dropdown" href="#" {{#if color}} style="border-color: {{color}}"{{/if}}>
                    <span class="short-label" title="{{label}}"{{#if color}} style="color: {{color}}"{{/if}}>
                        {{#if iconClass}}
                            <span class="{{iconClass}}"></span>
                        {{else}}
                            {{#if iconSrc}}
                                <img src="{{iconSrc}}" class="default-icon">
                            {{else}}
                                <span class="short-label-text">{{shortLabel}}</span>
                            {{/if}}
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
                                    {{#if iconSrc}}
                                        <img src="{{iconSrc}}" class="default-icon">
                                    {{else}}
                                        <span class="short-label-text">{{shortLabel}}</span>
                                    {{/if}}
                                {{/if}}
                            </span>
                            <span class="full-label">{{label}}</span>
                        </a>
                        <button data-action="quickCreate" title="{{translate "quickCreate"}}" data-name="{{name}}" class="quick-create btn btn-default btn-icon">
                    <i class="fas fa-plus fa-sm"></i>
                </button>
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
                        {{#if iconSrc}}
                            <img src="{{iconSrc}}" class="default-icon">
                        {{else}}
                            <span class="short-label-text">{{shortLabel}}</span>
                        {{/if}}
                    {{/if}}
                    </span>
                </a>
                <button data-action="quickCreate" title="{{translate "quickCreate"}}" data-name="{{name}}" class="quick-create btn btn-default btn-icon">
                    <i class="fas fa-plus fa-sm"></i>
                </button>
            </li>
            {{/if}}
            {{/each}}
        </ul>
        <footer>{{{footer}}}</footer>
    </div>

    <div class="collapse navbar-collapse navbar-body">
        {{#if globalSearch}}
            <div class="nav navbar-nav navbar-form global-search-container">
                {{{globalSearch}}}
            </div>
        {{/if}}

        <div class="nav navbar-nav navbar-left navbar-favorites">
            <div class="favorites-wrapper">
                <ul class="nav navbar-nav favorites-items">
                    {{#each favoritesList}}
                        <li data-name="{{name}}"><a href="{{link}}" class="favorite nav-link"{{#if color}} style="border-color: {{color}}"{{/if}} title="{{label}}"><span class="label-wrapper"><span class="favorite-icon {{iconClass}}"></span><span class="full-label">{{label}}</span></span></a></li>
                    {{/each}}
                </ul>
            </div>
        </div>

        <ul class="nav navbar-nav navbar-right navbar-dropdowns">
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
            <li class="dropdown notifications-badge-container hidden-xs">
                {{{notificationsBadge}}}
            </li>
            {{#if hasJM}}<li id='qqq' class="dropdown queue-badge-container hidden-xs"></li>{{/if}}
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
            <li class="dropdown hidden-xs favorites">
                <a href="javascript:" type="button" class="favorite show-more-button favorites-dropdown-btn dropdown-toggle" data-toggle="dropdown">
                    <span class="fas fa-star"></span>
                </a>
                <div class="dropdown-menu favorites-dropdown">
                    <div class="header">
                        <span>Favorites</span>
                        <span data-action="configureFavorites" class="fas fa-cog configure-btn" style="font-size: 1em;"></span>
                    </div>
                    <div role="separator" class="divider"></div>
                    <ul class="favorites-items">
                        {{#each favoritesList}}
                            <li data-name="{{name}}">
                                <a href="{{link}}" class="favorite nav-link"{{#if color}} style="border-color: {{color}}"{{/if}} title="{{label}}"><span class="label-wrapper"><span class="favorite-icon {{iconClass}}"></span><span class="full-label">{{label}}</span></span>
                                    <button data-action="quickCreate" title="{{translate "quickCreate"}}" data-name="{{name}}" class="quick-create btn btn-default btn-icon">
                                        <i class="fas fa-plus fa-sm"></i>
                                    </button>
                                </a>
                            </li>
                        {{/each}}
                    </ul>
                </div>
            </li>
        </ul>

    </div>
</div>
