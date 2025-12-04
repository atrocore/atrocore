<div class="navbar navbar-inverse" role="navigation" data-orientation="{{#if navbarIsVertical}}vertical{{else}}horizontal{{/if}}">
    <div class="navbar-header">
        <button type="button" class="navbar-toggle">
            <i class="ph ph-list"></i>
        </button>
        {{#if hasLogo }}
            <a class="navbar-brand nav-link" href="#"><img src="{{logoSrc}}" class="logo"><span class="home-icon ph ph-house" title="{{translate 'homepage'}}"></span></a>
        {{/if}}
        <div class="navbar-header-inner pull-right">
            {{#if globalSearch}}
            <button type="button" class="search-toggle pull-left visible-xs">
                <i class="ph ph-magnifying-glass"></i>
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
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><i class="ph ph-user"></i></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" {{#if targetBlank}} target="_blank" {{/if}} class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}{{#if title}} title="{{title}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{#if icon}}{{{icon}}}{{/if}}{{label}}{{/if}}</a></li>
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
                <i data-action="configureMenu" class="ph ph-gear cursor-pointer"></i>
                {{/if}}
            </li>
            {{#each tabDefsList}}
            {{#if group}}
            <li class="dropdown more more-group tab">
                <a id="nav-more-tabs-dropdown-{{id}}" class="dropdown-toggle more-group-name" data-toggle="dropdown" href="#" title="{{label}}" {{#if color}} style="border-color: {{color}}"{{/if}}>
                    <span class="short-label" {{#if color}} style="color: {{color}}"{{/if}}>
                       {{#if ../showIcon }}
                            {{#if iconSrc}}
                                <img src="{{iconSrc}}" class="icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                            {{else}}
                                {{#if defaultIconSrc}}
                                    <img src="{{defaultIconSrc}}" class="default-icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                                {{/if}}
                            {{/if}}
                        {{/if}}
                    </span>
                    <span class="full-label" {{#unless ../showIcon }} style="margin-left: auto !important;" {{/unless}}>{{label}}</span>
                    <i class="ph ph-caret-down"></i>
                </a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-more-tabs-dropdown-{{id}}">
                    {{#each items}}
                    <li data-name="{{name}}" class="in-more tab">
                        <a href="{{link}}" class="nav-link" style="{{#if color}} border-color: {{color}} ; {{/if}}   {{#unless ../../showIcon }} height: 35px; {{/unless}}" title="{{label}}">
                            <span class="short-label" {{#if color}} style="color: {{color}}"{{/if}}>
                                 {{#if ../../showIcon }}
                                    {{#if iconSrc}}
                                        <img src="{{iconSrc}}" alt="" class="icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                                    {{else}}
                                        {{#if defaultIconSrc}}
                                            <img src="{{defaultIconSrc}}" alt="" class="default-icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                                        {{/if}}
                                    {{/if}}
                                {{/if}}
                            </span>
                            <span class="full-label" {{#unless ../../showIcon }} style="margin-left: auto !important;" {{/unless}}>{{label}}</span>
                            {{#unless createDisabled}}
                                <button data-action="quickCreate" title="{{translate "quickCreate"}}" data-name="{{name}}" class="quick-create btn btn-default btn-icon">
                                    <i class="ph ph-plus"></i>
                                </button>
                            {{/unless}}
                        </a>
                    </li>
                    {{/each}}
                </ul>
            </li>
            {{else}}
            <li data-name="{{name}}" class="not-in-more tab">
                <a href="{{link}}" class="nav-link"{{#if color}} style="border-color: {{color}}"{{/if}} title="{{label}}">
                    <span class="short-label" {{#if color}} style="color: {{color}}"{{/if}}>
                        {{#if ../showIcon }}
                            {{#if iconSrc}}
                                <img src="{{iconSrc}}" alt="" class="icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                            {{else}}
                                {{#if defaultIconSrc}}
                                    <img src="{{defaultIconSrc}}" alt="" class="default-icon" {{#if colorFilter}} style="{{{colorFilter}}}"{{/if}}>
                                {{/if}}
                            {{/if}}
                        {{/if}}
                    </span>
                    <span class="full-label" {{#unless ../showIcon }} style="margin-left: auto !important;" {{/unless}} >{{label}}</span>
                    {{#unless createDisabled}}
                        <button data-action="quickCreate" title="{{translate "quickCreate"}}" data-name="{{name}}" class="quick-create btn btn-default btn-icon">
                            <i class="ph ph-plus"></i>
                        </button>
                    {{/unless}}
                </a>
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
        {{#if hasLocaleSwitcher}}
        <div class="locale-switcher-container"></div>
        {{/if}}

        <div class="nav navbar-nav navbar-left navbar-favorites">
            <div class="favorites-wrapper">{{{favoritesToolbar}}}</div>
        </div>

        <ul class="nav navbar-nav navbar-right navbar-dropdowns">
            <li class="dropdown menu-container hidden-xs">
                <a id="nav-menu-dropdown" class="dropdown-toggle" data-toggle="dropdown" href="#" title="{{translate 'Menu'}}"><i class="ph ph-user"></i></a>
                <ul class="dropdown-menu" role="menu" aria-labelledby="nav-menu-dropdown" style="min-width: 230px;">
                    {{#each menuDataList}}
                    {{#unless divider}}
                    <li><a href="{{#if link}}{{link}}{{else}}javascript:{{/if}}" {{#if targetBlank}} target="_blank" {{/if}} class="nav-link{{#if action}} action{{/if}}"{{#if action}} data-action="{{action}}"{{/if}}{{#if title}} title="{{title}}"{{/if}}>{{#if html}}{{{html}}}{{else}}{{#if icon}}{{{icon}}}{{/if}}{{label}}{{/if}}</a></li>
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
                    <i class="ph ph-star"></i>
                </a>
                <div class="dropdown-menu favorites-dropdown">
                    <div class="header">
                        <span>Favorites</span>
                        <i class="ph ph-gear configure-btn" data-action="configureFavorites"></i>
                    </div>
                    <div role="separator" class="divider"></div>
                    <div class="wrapper">
                        {{{favoritesListDropdown}}}
                    </div>
                </div>
            </li>
        </ul>

    </div>
</div>
